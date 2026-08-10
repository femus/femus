<?php

declare(strict_types=1);

namespace Femus\Gsm\Gateway;

/**
 * Answers SMS questions via the Claude Messages API (raw HTTP — no SDK, keeping
 * femus dependency-free). The box runs this over its home internet; the phone only
 * needs SMS. Swap in an official-SDK-backed {@see AiClient} if you prefer.
 */
final class ClaudeAiClient implements AiClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly HttpClient $http = new CurlHttpClient(),
        private readonly string $model = 'claude-opus-4-8',
        private readonly int $maxReplyChars = 300,
    ) {
    }

    public function ask(string $question): string
    {
        $system = 'You answer questions relayed over SMS from a phone with no internet. '
            . "Reply in plain text only — no markdown, no lists, no links. Be direct and "
            . "keep it under {$this->maxReplyChars} characters. If you are unsure, say so briefly.";

        $body = json_encode([
            'model' => $this->model,
            'max_tokens' => 512,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $question]],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = $this->http->postJson(self::ENDPOINT, [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
            'content-type' => 'application/json',
        ], (string) $body);

        if ($response['status'] !== 200) {
            throw new \RuntimeException("Claude API error {$response['status']}: {$response['body']}");
        }

        $data = json_decode($response['body'], true);
        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'];
            }
        }

        return trim($text);
    }
}
