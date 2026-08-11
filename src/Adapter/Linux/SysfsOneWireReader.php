<?php

declare(strict_types=1);

namespace Femus\Adapter\Linux;

use Femus\Contracts\OneWireReader;

/**
 * Reads 1-Wire devices through the Linux kernel w1 sysfs interface, the way a
 * Raspberry Pi exposes DS18B20 sensors once `dtoverlay=w1-gpio` is enabled.
 *
 * Devices live at /sys/bus/w1/devices/28-*, each with a `w1_slave` file.
 */
final class SysfsOneWireReader implements OneWireReader
{
    public function __construct(
        private readonly string $basePath = '/sys/bus/w1/devices',
    ) {
    }

    public function devices(): array
    {
        // DS18B20 sensors report the family code 28-*.
        $matches = glob($this->basePath . '/28-*', GLOB_ONLYDIR) ?: [];

        return array_map('basename', $matches);
    }

    public function read(string $deviceId): string
    {
        $path = $this->basePath . '/' . $deviceId . '/w1_slave';
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("Cannot read 1-Wire device at {$path}. Is dtoverlay=w1-gpio enabled?");
        }

        return $contents;
    }
}
