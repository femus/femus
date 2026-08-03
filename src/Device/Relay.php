<?php

declare(strict_types=1);

namespace Femus\Device;

use Femus\Contracts\DigitalPin;

final class Relay
{
    public function __construct(private readonly DigitalPin $pin)
    {
    }

    public function on(): void
    {
        $this->pin->write(true);
    }

    public function off(): void
    {
        $this->pin->write(false);
    }

    public function toggle(): void
    {
        $this->pin->write(!$this->pin->read());
    }

    public function isOn(): bool
    {
        return $this->pin->read();
    }
}
