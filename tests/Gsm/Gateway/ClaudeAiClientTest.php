<?php

declare(strict_types=1);

use Femus\Gsm\Gateway\ClaudeAiClient;
use Femus\Gsm\Gateway\HttpClient;

/** Records the request and returns a canned Anthropic Messages API response. */
final class FakeHttpClient implements HttpClient
{
    public ?string $url = null;
    /** @var array<string, string> */
    public array $headers = [];
    public ?string $body = null;

    /** @param array{status: int, body: string} $response */
    public function __construct(private readonly array $response)
    {
    }

    public function postJson(string $url, array $headers, string $body): array
    {
        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;

        return $this->response;
    }
}

function anthropicOk(string $text): array
{
    return [
        'status' => 200,
        'body' => json_encode([
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => 'end_turn',
        ]),
    ];
}

it('sends the question and returns the answer text', function () {
    $http = new FakeHttpClient(anthropicOk('It is 21°C in Halifax.'));
    $answer = (new ClaudeAiClient('sk-test', $http))->ask('weather in Halifax?');

    expect($answer)->toBe('It is 21°C in Halifax.');

    $sent = json_decode((string) $http->body, true);
    expect($sent['model'])->toBe('claude-opus-4-8')
        ->and($sent['messages'][0])->toBe(['role' => 'user', 'content' => 'weather in Halifax?'])
        ->and($http->headers['x-api-key'])->toBe('sk-test')
        ->and($http->headers['anthropic-version'])->toBe('2023-06-01')
        ->and($http->url)->toBe('https://api.anthropic.com/v1/messages');
});

it('honours a custom model', function () {
    $http = new FakeHttpClient(anthropicOk('hi'));
    (new ClaudeAiClient('sk-test', $http, model: 'claude-haiku-4-5'))->ask('hi');

    expect(json_decode((string) $http->body, true)['model'])->toBe('claude-haiku-4-5');
});

it('concatenates multiple text blocks', function () {
    $http = new FakeHttpClient([
        'status' => 200,
        'body' => json_encode(['content' => [
            ['type' => 'text', 'text' => 'part one '],
            ['type' => 'text', 'text' => 'part two'],
        ]]),
    ]);
    expect((new ClaudeAiClient('sk-test', $http))->ask('x'))->toBe('part one part two');
});

it('throws on a non-200 response', function () {
    $http = new FakeHttpClient(['status' => 401, 'body' => '{"error":{"message":"bad key"}}']);
    expect(fn () => (new ClaudeAiClient('sk-test', $http))->ask('x'))
        ->toThrow(RuntimeException::class, '401');
});
