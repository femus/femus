<?php

declare(strict_types=1);

use Femus\Mcp\McpServer;
use Femus\Mcp\McpToolException;
use Femus\Mcp\ToolRegistry;

function runMcp(array $requests, ?ToolRegistry $tools = null): array
{
    $input = fopen('php://memory', 'r+');
    foreach ($requests as $request) {
        fwrite($input, json_encode($request) . "\n");
    }
    rewind($input);

    $output = fopen('php://memory', 'r+');
    (new McpServer($input, $output, $tools ?? new ToolRegistry()))->run();

    rewind($output);
    $responses = [];
    while (($line = fgets($output)) !== false) {
        $responses[] = json_decode(trim($line), true);
    }

    return $responses;
}

it('answers initialize with capabilities and server info', function () {
    [$response] = runMcp([
        ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-03-26']],
    ]);

    expect($response['id'])->toBe(1)
        ->and($response['result']['protocolVersion'])->toBe('2025-03-26')
        ->and($response['result']['serverInfo']['name'])->toBe('femus')
        ->and($response['result']['capabilities'])->toHaveKey('tools');
});

it('ignores notifications and lists registered tools', function () {
    $tools = new ToolRegistry();
    $tools->register('hello', 'Says hello.', ['type' => 'object'], fn () => 'hi');

    $responses = runMcp([
        ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
        ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
    ], $tools);

    expect($responses)->toHaveCount(1)
        ->and($responses[0]['result']['tools'][0]['name'])->toBe('hello');
});

it('calls a tool and wraps the result as text content', function () {
    $tools = new ToolRegistry();
    $tools->register('echo', 'Echoes.', ['type' => 'object'], fn (array $a) => 'got: ' . $a['msg']);

    [$response] = runMcp([
        ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'echo', 'arguments' => ['msg' => 'ping']]],
    ], $tools);

    expect($response['result']['isError'])->toBeFalse()
        ->and($response['result']['content'][0])->toBe(['type' => 'text', 'text' => 'got: ping']);
});

it('reports tool failures as isError results, not protocol errors', function () {
    $tools = new ToolRegistry();
    $tools->register('boom', 'Fails.', ['type' => 'object'], function () {
        throw new McpToolException('hardware went missing');
    });

    [$response] = runMcp([
        ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'boom', 'arguments' => []]],
    ], $tools);

    expect($response)->not->toHaveKey('error')
        ->and($response['result']['isError'])->toBeTrue()
        ->and($response['result']['content'][0]['text'])->toContain('hardware went missing');
});

it('returns JSON-RPC errors for unknown methods and unknown tools', function () {
    $responses = runMcp([
        ['jsonrpc' => '2.0', 'id' => 5, 'method' => 'bogus/method'],
        ['jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call', 'params' => ['name' => 'nope']],
    ]);

    expect($responses[0]['error']['code'])->toBe(-32601)
        ->and($responses[1]['error']['code'])->toBe(-32602);
});
