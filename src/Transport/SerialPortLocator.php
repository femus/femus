<?php

declare(strict_types=1);

namespace Femus\Transport;

final class SerialPortLocator
{
    private \Closure $glob;

    public function __construct(?\Closure $glob = null)
    {
        $this->glob = $glob ?? static fn (string $pattern): array => glob($pattern) ?: [];
    }

    /** @return list<string> */
    public function candidates(): array
    {
        $patterns = PHP_OS_FAMILY === 'Darwin'
            ? [
                '/dev/cu.usbserial*',
                '/dev/cu.usbmodem*',
                '/dev/cu.wchusbserial*',
                '/dev/cu.*',
            ]
            : [
                '/dev/ttyUSB*',
                '/dev/ttyACM*',
                '/dev/rfcomm*',
            ];

        $excluded = ['Bluetooth-Incoming', 'debug', 'wlan'];

        $found = [];
        foreach ($patterns as $pattern) {
            foreach (($this->glob)($pattern) as $port) {
                foreach ($excluded as $substring) {
                    if (str_contains($port, $substring)) {
                        continue 2;
                    }
                }
                $found[] = $port;
            }
        }

        $found = array_values(array_unique($found));
        sort($found);

        return $found;
    }
}
