<?php

declare(strict_types=1);

namespace Femus\Gsm\Gateway;

/**
 * Minimal HTTP POST abstraction so the Claude client is testable without a network.
 * The real implementation ({@see CurlHttpClient}) uses PHP's built-in curl — femus
 * keeps zero production dependencies, so no SDK is pulled in.
 */
interface HttpClient
{
    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     */
    public function postJson(string $url, array $headers, string $body): array;
}
