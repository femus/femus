<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

final class Hx711Reading
{
    public function __construct(public readonly int $value)
    {
    }

    /** Parses a sysex payload; null when it is not an HX711 reading frame. */
    public static function fromSysexPayload(string $payload): ?self
    {
        if (strlen($payload) < 7
            || ord($payload[0]) !== Firmata::FEMUS_HX711
            || ord($payload[1]) !== Firmata::HX711_READING) {
            return null;
        }
        $value = 0;
        for ($i = 0; $i < 5; $i++) {
            $value |= (ord($payload[2 + $i]) & 0x7F) << (7 * $i);
        }
        $value &= 0xFFFFFFFF;
        if ($value >= 0x80000000) {
            $value -= 0x100000000;
        }

        return new self($value);
    }
}
