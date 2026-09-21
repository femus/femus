<?php

declare(strict_types=1);

namespace Femus\Gsm\Gateway;

/**
 * Answers SMS questions through any OpenAI-compatible chat-completions endpoint —
 * Gemini, Groq, OpenRouter, Cerebras, a local Ollama. Only the base URL, the model
 * and the key change; the wire format is the same, so one client covers all of them.
 *
 * Gemini (free tier):
 *   new OpenAiCompatibleAiClient(getenv('GEMINI_API_KEY'), model: 'gemini-3.6-flash')
 * Groq:
 *   new OpenAiCompatibleAiClient($key, endpoint: 'https://api.groq.com/openai/v1/chat/completions',
 *       model: 'llama-3.3-70b-versatile')
 */
final class OpenAiCompatibleAiClient implements AiClient
{
    public const GEMINI = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';

    public function __construct(
        private readonly string $apiKey,
        private readonly HttpClient $http = new CurlHttpClient(),
        private readonly string $model = 'gemini-3.6-flash',
        private readonly string $endpoint = self::GEMINI,
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
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $question],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = $this->http->postJson($this->endpoint, [
            'authorization' => "Bearer {$this->apiKey}",
            'content-type' => 'application/json',
        ], (string) $body);

        if ($response['status'] !== 200) {
            throw new \RuntimeException("AI API error {$response['status']}: {$response['body']}");
        }

        $data = json_decode($response['body'], true);
        $text = $data['choices'][0]['message']['content'] ?? null;

        if (!is_string($text) || trim($text) === '') {
            throw new \RuntimeException("AI API returned no answer: {$response['body']}");
        }

        return trim($text);
    }
}
