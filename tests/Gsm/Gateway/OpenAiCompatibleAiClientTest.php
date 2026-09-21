<?php

declare(strict_types=1);

use Femus\Gsm\Gateway\OpenAiCompatibleAiClient;

function chatOk(string $text): array
{
    return [
        'status' => 200,
        'body' => json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => $text]]]]),
    ];
}

it('sends the question and returns the answer text', function () {
    $http = new FakeHttpClient(chatOk('It is 21°C in Halifax.'));
    $answer = (new OpenAiCompatibleAiClient('key-test', $http))->ask('weather in Halifax?');

    expect($answer)->toBe('It is 21°C in Halifax.');

    $sent = json_decode((string) $http->body, true);
    expect($sent['model'])->toBe('gemini-3.6-flash')
        ->and($sent['messages'][0]['role'])->toBe('system')
        ->and($sent['messages'][1])->toBe(['role' => 'user', 'content' => 'weather in Halifax?'])
        ->and($http->headers['authorization'])->toBe('Bearer key-test')
        ->and($http->url)->toBe(OpenAiCompatibleAiClient::GEMINI);
});

it('talks to any other OpenAI-compatible endpoint', function () {
    $http = new FakeHttpClient(chatOk('hi'));
    (new OpenAiCompatibleAiClient(
        'key-test',
        $http,
        model: 'llama-3.3-70b-versatile',
        endpoint: 'https://api.groq.com/openai/v1/chat/completions',
    ))->ask('hi');

    expect(json_decode((string) $http->body, true)['model'])->toBe('llama-3.3-70b-versatile')
        ->and($http->url)->toBe('https://api.groq.com/openai/v1/chat/completions');
});

it('throws on a non-200 response', function () {
    $http = new FakeHttpClient(['status' => 401, 'body' => '{"error":{"message":"bad key"}}']);
    expect(fn () => (new OpenAiCompatibleAiClient('key-test', $http))->ask('x'))
        ->toThrow(RuntimeException::class, '401');
});

it('throws when the reply has no content', function () {
    $http = new FakeHttpClient(['status' => 200, 'body' => '{"choices":[]}']);
    expect(fn () => (new OpenAiCompatibleAiClient('key-test', $http))->ask('x'))
        ->toThrow(RuntimeException::class, 'no answer');
});
