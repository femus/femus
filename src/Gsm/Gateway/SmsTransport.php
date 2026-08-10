<?php

declare(strict_types=1);

namespace Femus\Gsm\Gateway;

/**
 * Splits an arbitrary payload into SMS-sized segments for the femus↔femus packet
 * mode. A payload that fits in one segment is sent header-less (a normal SMS any
 * phone can read); larger payloads get "[id:seq/total]" headers that a peer running
 * {@see SmsReassembler} stitches back together.
 */
final class SmsTransport
{
    /** @return list<string> */
    public function chunk(string $payload, string $id, int $maxLen = 150): array
    {
        if (strlen($payload) <= $maxLen) {
            return [$payload];
        }

        // Reserve room for the largest header this message can produce.
        $parts = str_split($payload, max(1, $maxLen - strlen("[{$id}:999/999]")));
        $total = count($parts);

        $out = [];
        foreach ($parts as $i => $part) {
            $out[] = sprintf('[%s:%d/%d]%s', $id, $i + 1, $total, $part);
        }

        return $out;
    }
}
