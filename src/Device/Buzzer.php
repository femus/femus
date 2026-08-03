<?php

declare(strict_types=1);

namespace Femus\Device;

use Femus\Contracts\DigitalPin;
use Femus\Runtime\Loop;

final class Buzzer
{
    public function __construct(
        private readonly DigitalPin $pin,
        private readonly Loop $loop,
    ) {
    }

    public function on(): void
    {
        $this->pin->write(true);
    }

    public function off(): void
    {
        $this->pin->write(false);
    }

    public function isOn(): bool
    {
        return $this->pin->read();
    }

    public function beep(float $seconds = 0.1): void
    {
        $this->on();
        $this->loop->addTimer($seconds, $this->off(...));
    }
}
