<?php

declare(strict_types=1);

namespace Femus\Device;

use Femus\Contracts\DigitalPin;

/**
 * HD44780 16x2 LCD wired directly to GPIO pins (4-bit mode, no I2C backpack). RW must be tied to GND.
 */
final class Lcd1602Parallel
{
    public function __construct(
        private readonly DigitalPin $rs,
        private readonly DigitalPin $en,
        private readonly DigitalPin $d4,
        private readonly DigitalPin $d5,
        private readonly DigitalPin $d6,
        private readonly DigitalPin $d7,
    ) {
        // HD44780 4-bit initialization sequence (datasheet)
        $this->command(0x33); // wake + ensure 4-bit mode step 1
        $this->command(0x32); // switch to 4-bit mode step 2
        $this->command(0x28); // 4 bits, 2 lines, 5x8 font
        $this->command(0x0C); // display on, cursor off, blink off
        $this->command(0x06); // entry mode: cursor increment, no display shift
        $this->command(0x01); // clear display
        usleep(2000);         // clear requires >1.52 ms
    }

    public function clear(): void
    {
        $this->command(0x01);
        usleep(2000);
    }

    public function setCursor(int $col, int $row): void
    {
        $this->command(0x80 | $col | ($row === 0 ? 0x00 : 0x40));
    }

    public function write(string $text): void
    {
        foreach (str_split($text) as $char) {
            $this->send(ord($char), rs: true);
        }
    }

    private function command(int $byte): void
    {
        $this->send($byte, rs: false);
    }

    private function send(int $byte, bool $rs): void
    {
        $this->pulseNibble(($byte >> 4) & 0x0F, $rs); // high nibble first
        $this->pulseNibble($byte & 0x0F, $rs);          // low nibble second
    }

    private function pulseNibble(int $nibble, bool $rs): void
    {
        $this->rs->write($rs);
        $this->d4->write((bool) ($nibble & 0x1));
        $this->d5->write((bool) ($nibble & 0x2));
        $this->d6->write((bool) ($nibble & 0x4));
        $this->d7->write((bool) ($nibble & 0x8));
        $this->en->write(true);
        usleep(1);
        $this->en->write(false);
        usleep(50); // HD44780 needs >37 µs per operation
    }
}
