<?php

declare(strict_types=1);

namespace Femus\Adapter\Fake;

use Femus\Contracts\OneWireReader;

/**
 * In-memory 1-Wire bus for hardware-free tests. Seed it with raw w1_slave
 * contents per device id, or use {@see setCelsius()} to generate a well-formed
 * reading for a given temperature.
 */
final class FakeOneWireReader implements OneWireReader
{
    /** @var array<string, string> */
    private array $files = [];

    public function devices(): array
    {
        return array_keys($this->files);
    }

    public function read(string $deviceId): string
    {
        return $this->files[$deviceId]
            ?? throw new \RuntimeException("No fake 1-Wire device {$deviceId}.");
    }

    /** Set the raw w1_slave contents for a device. */
    public function setRaw(string $deviceId, string $contents): void
    {
        $this->files[$deviceId] = $contents;
    }

    /** Generate a valid (CRC YES) reading for the given temperature. */
    public function setCelsius(string $deviceId, float $celsius): void
    {
        $milli = (int) round($celsius * 1000);
        $this->files[$deviceId] = "ce 01 4b 46 7f ff 0c 10 6e : crc=6e YES\n"
            . "ce 01 4b 46 7f ff 0c 10 6e t={$milli}\n";
    }

    /** Generate a bad-CRC (NO) reading to simulate a failed read. */
    public function setBadCrc(string $deviceId): void
    {
        $this->files[$deviceId] = "ce 01 4b 46 7f ff 0c 10 6e : crc=6e NO\n"
            . "ce 01 4b 46 7f ff 0c 10 6e t=85000\n";
    }
}
