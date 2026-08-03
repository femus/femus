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

    public static function i2cConfig(int $delayMicros = 0): string
    {
        return chr(Firmata::SYSEX_START) . chr(Firmata::I2C_CONFIG)
            . chr($delayMicros & 0x7F) . chr(($delayMicros >> 7) & 0x7F)
            . chr(Firmata::SYSEX_END);
    }

    public static function i2cWrite(int $address, string $bytes): string
    {
        return chr(Firmata::SYSEX_START) . chr(Firmata::I2C_REQUEST)
            . chr($address) . chr(Firmata::I2C_MODE_WRITE)
            . self::encode7bitPairs($bytes)
            . chr(Firmata::SYSEX_END);
    }

    public static function i2cReadRegister(int $address, int $register, int $length): string
    {
        return chr(Firmata::SYSEX_START) . chr(Firmata::I2C_REQUEST)
            . chr($address) . chr(Firmata::I2C_MODE_READ_ONCE)
            . chr($register & 0x7F) . chr(($register >> 7) & 0x7F)
            . chr($length & 0x7F) . chr(($length >> 7) & 0x7F)
            . chr(Firmata::SYSEX_END);
    }

    /** Каждый байт → пара 7-битных: LSB, затем старший бит. */
    private static function encode7bitPairs(string $bytes): string
    {
        $out = '';
        foreach (str_split($bytes) as $byte) {
            $value = ord($byte);
            $out .= chr($value & 0x7F) . chr(($value >> 7) & 0x7F);
        }

        return $out;
    }

    private function __construct()
    {
    }
}
