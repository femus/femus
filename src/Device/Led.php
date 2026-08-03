<?php

declare(strict_types=1);

namespace Femus\Device;

use Femus\Contracts\DigitalPin;
use Femus\Runtime\Loop;

final class Led
{
    private ?string $blinkTimer = null;

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

    public function toggle(): void
    {
        $this->pin->write(!$this->pin->read());
    }

    public function isOn(): bool
    {
        return $this->pin->read();
    }

    public function blink(float $intervalSeconds = 0.5): void
    {
        $this->stopBlinking();
        $this->blinkTimer = $this->loop->addPeriodicTimer($intervalSeconds, $this->toggle(...));
    }

    public function stopBlinking(): void
    {
        if ($this->blinkTimer !== null) {
            $this->loop->cancelTimer($this->blinkTimer);
            $this->blinkTimer = null;
        }
    }
}
