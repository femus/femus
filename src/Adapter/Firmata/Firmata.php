<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

/** Константы протокола Firmata 2.x. */
final class Firmata
{
    public const REPORT_VERSION = 0xF9;
    public const SET_PIN_MODE = 0xF4;
    public const DIGITAL_MESSAGE = 0x90; // | номер порта (8 пинов на порт)
    public const REPORT_DIGITAL = 0xD0;  // | номер порта
    public const SYSEX_START = 0xF0;
    public const SYSEX_END = 0xF7;

    public const MODE_INPUT = 0x00;
    public const MODE_OUTPUT = 0x01;
    public const MODE_PULLUP = 0x0B;

    private function __construct()
    {
    }
}
