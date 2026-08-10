<?php

declare(strict_types=1);

namespace Femus\Gsm\Gateway;

/**
 * Stitches "[id:seq/total]" segments produced by {@see SmsTransport} back into the
 * original payload. A segment without a header is a standalone SMS and is returned
 * as-is. Returns null while a multi-part message is still incomplete.
 */
final class SmsReassembler
{
    /** @var array<string, array<int, string>> id => (seq => data) */
    private array $buffers = [];

    /** @var array<string, int> id => expected total */
    private array $totals = [];

    public function add(string $segment): ?string
    {
        if (preg_match('/^\[([^:\]]+):(\d+)\/(\d+)\](.*)$/s', $segment, $m) !== 1) {
            return $segment;
        }

        [, $id, $seq, $total, $data] = $m;
        $this->buffers[$id][(int) $seq] = $data;
        $this->totals[$id] = (int) $total;

        if (count($this->buffers[$id]) < $this->totals[$id]) {
            return null;
        }

        ksort($this->buffers[$id]);
        $full = implode('', $this->buffers[$id]);
        unset($this->buffers[$id], $this->totals[$id]);

        return $full;
    }
}
