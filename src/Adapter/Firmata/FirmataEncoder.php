<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

final class FirmataEncoder
{
    public static function setPinMode(int $pin, int $mode): string
    {
        return chr(Firmata::SET_PIN_MODE) . chr($pin) . chr($mode);
    }

    /** Firmata transmits 14-bit values as 7-bit pairs: lower 7 bits first, then upper. */
    public static function digitalWrite(int $port, int $bitmask): string
    {
        return chr(Firmata::DIGITAL_MESSAGE | $port)
            . chr($bitmask & 0x7F)
            . chr(($bitmask >> 7) & 0x7F);
    }

    /** PWM duty for a pin (0–255), sent as a 7-bit pair. Pin must be in PWM mode. */
    public static function analogWrite(int $pin, int $value): string
    {
        return chr(Firmata::ANALOG_MESSAGE | ($pin & 0x0F))
            . chr($value & 0x7F)
            . chr(($value >> 7) & 0x7F);
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

    public static function hx711Attach(int $doutPin, int $sckPin): string
    {
        return chr(Firmata::SYSEX_START) . chr(Firmata::FEMUS_HX711) . chr(Firmata::HX711_ATTACH)
            . chr($doutPin) . chr($sckPin)
            . chr(Firmata::SYSEX_END);
    }

    public static function radioAttach(int $address, int $rxPin, int $txPin): string
    {
        return chr(Firmata::SYSEX_START) . chr(Firmata::FEMUS_RADIO) . chr(Firmata::RADIO_ATTACH)
            . chr($address) . chr($rxPin) . chr($txPin)
            . chr(Firmata::SYSEX_END);
    }

    public static function radioSend(int $toAddress, string $message): string
    {
        return chr(Firmata::SYSEX_START) . chr(Firmata::FEMUS_RADIO) . chr(Firmata::RADIO_SEND)
            . chr($toAddress)
            . self::encode7bitPairs($message)
            . chr(Firmata::SYSEX_END);
    }

    /** Each byte → a 7-bit pair: LSB first, then the high bit. */
    public static function encode7bitPairs(string $bytes): string
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
