<?php

declare(strict_types=1);

namespace Femus\Device;

use Femus\Contracts\I2cBus;

/**
 * LCD 16x2 on HD44780 with I2C backpack PCF8574.
 * PCF8574 byte: bit0=RS, bit1=RW, bit2=EN, bit3=backlight, bits4-7=nibble.
 */
final class Lcd1602
{
    private const RS = 0x01;
    private const EN = 0x04;
    private const BACKLIGHT = 0x08;

    private bool $backlightOn = true;

    public function __construct(
        private readonly I2cBus $bus,
        private readonly int $address = 0x27,
    ) {
        // Init HD44780 in 4-bit mode (datasheet, sequence via 0x33/0x32)
        $this->command(0x33);
        $this->command(0x32);
        $this->command(0x28); // 4 bits, 2 lines, 5x8
        $this->command(0x0C); // display on, cursor off
        $this->command(0x06); // cursor increment
        $this->command(0x01); // clear
        usleep(2000);         // clear requires >1.52 ms
    }

    public function clear(): void
    {
        $this->command(0x01);
        usleep(2000);
    }

    public function setCursor(int $col, int $row): void
    {
        $this->command(0x80 | ($col + ($row === 0 ? 0x00 : 0x40)));
    }

    public function write(string $text): void
    {
        foreach (str_split($text) as $char) {
            $this->send(ord($char), self::RS);
        }
    }

    public function backlight(bool $on): void
    {
        $this->backlightOn = $on;
    }

    private function command(int $byte): void
    {
        $this->send($byte, 0x00);
    }

    private function send(int $byte, int $flags): void
    {
        $this->pulseNibble(($byte >> 4) & 0x0F, $flags);
        $this->pulseNibble($byte & 0x0F, $flags);
    }

    private function pulseNibble(int $nibble, int $flags): void
    {
        $base = ($nibble << 4) | $flags | ($this->backlightOn ? self::BACKLIGHT : 0x00);
        $this->bus->write($this->address, chr($base | self::EN));
        $this->bus->write($this->address, chr($base));
        usleep(50); // HD44780 requires >37 µs per command
    }
}
