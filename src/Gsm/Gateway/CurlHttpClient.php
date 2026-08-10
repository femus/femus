<?php

declare(strict_types=1);

namespace Femus\Gsm\Gateway;

/** HTTP POST over PHP's built-in curl. No external dependencies. */
final class CurlHttpClient implements HttpClient
{
    public function __construct(private readonly int $timeoutSeconds = 30)
    {
    }

    public function postJson(string $url, array $headers, string $body): array
    {
        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = "{$name}: {$value}";
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new \RuntimeException("HTTP request failed: {$error}");
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => (string) $response];
    }
}
