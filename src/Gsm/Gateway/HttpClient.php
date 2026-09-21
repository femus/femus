<?php

declare(strict_types=1);

namespace Femus\Gsm\Gateway;

/**
 * Minimal HTTP abstraction so the AI client and the commands are testable without a network.
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

    /**
     * Plain GET, for the read-only APIs a gateway command leans on.
     *
     * @return array{status: int, body: string}
     */
    public function get(string $url): array;
}
