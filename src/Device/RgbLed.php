<?php

declare(strict_types=1);

namespace Femus\Device;

use Femus\Contracts\PwmPin;

/** A common-cathode RGB LED on three PWM pins. */
final class RgbLed
{
    public function __construct(
        private readonly PwmPin $red,
        private readonly PwmPin $green,
        private readonly PwmPin $blue,
    ) {
    }

    /** Each channel 0–255. */
    public function color(int $red, int $green, int $blue): void
    {
        $this->red->write($red);
        $this->green->write($green);
        $this->blue->write($blue);
    }

    /** Hex string like "#ff8800" or "ff8800". */
    public function hex(string $hex): void
    {
        $hex = ltrim($hex, '#');
        $this->color(
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        );
    }

    public function off(): void
    {
        $this->color(0, 0, 0);
    }
}
