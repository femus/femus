<?php

declare(strict_types=1);

namespace Femus\Device;

use Femus\Contracts\OneWireReader;

/**
 * A Dallas DS18B20 1-Wire temperature sensor (as read via the Linux w1 kernel
 * driver on a Raspberry Pi).
 *
 * The kernel exposes each sensor's reading as a two-line `w1_slave` file:
 *
 *     ce 01 4b 46 7f ff 0c 10 6e : crc=6e YES
 *     ce 01 4b 46 7f ff 0c 10 6e t=28875
 *
 * The first line ends in YES/NO (the CRC check); the second carries the
 * temperature in millidegrees Celsius after `t=`.
 */
final class Ds18b20
{
    public function __construct(
        private readonly OneWireReader $reader,
        private readonly string $deviceId,
    ) {
    }

    public function id(): string
    {
        return $this->deviceId;
    }

    /** Temperature in °C, or null if the sensor reported a bad CRC / unparseable read. */
    public function celsius(): ?float
    {
        $raw = $this->reader->read($this->deviceId);

        // The CRC line must end in YES for the reading to be trustworthy.
        if (! preg_match('/crc=[0-9a-f]+ YES/i', $raw)) {
            return null;
        }

        if (! preg_match('/t=(-?\d+)/', $raw, $m)) {
            return null;
        }

        return (int) $m[1] / 1000.0;
    }

    /** Temperature in °F, or null on a bad read. */
    public function fahrenheit(): ?float
    {
        $celsius = $this->celsius();

        return $celsius === null ? null : $celsius * 9 / 5 + 32;
    }
}
