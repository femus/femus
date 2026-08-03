<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

final class FirmataEncoder
{
    public static function setPinMode(int $pin, int $mode): string
    {
        return chr(Firmata::SET_PIN_MODE) . chr($pin) . chr($mode);
    }

    /** Firmata передаёт 14-битные значения по 7 бит: младшие 7, затем старшие. */
    public static function digitalWrite(int $port, int $bitmask): string
    {
        return chr(Firmata::DIGITAL_MESSAGE | $port)
            . chr($bitmask & 0x7F)
            . chr(($bitmask >> 7) & 0x7F);
    }

    public static function reportDigitalPort(int $port, bool $enable): string
    {
        return chr(Firmata::REPORT_DIGITAL | $port) . chr($enable ? 1 : 0);
    }

    public static function reportAnalogChannel(int $channel, bool $enable): string
    {
        return chr(Firmata::REPORT_ANALOG | $channel) . chr($enable ? 1 : 0);
    }

    private function __construct()
    {
    }
}
