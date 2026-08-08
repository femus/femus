<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

use Femus\Contracts\PwmPin;
use Femus\Transport\Transport;

final class FirmataPwmPin implements PwmPin
{
    public function __construct(
        private readonly Transport $transport,
        private readonly int $pin,
    ) {
        $transport->write(FirmataEncoder::setPinMode($pin, Firmata::MODE_PWM));
    }

    public function pin(): int
    {
        return $this->pin;
    }

    public function write(int $value): void
    {
        $value = max(0, min(255, $value));
        $this->transport->write(FirmataEncoder::analogWrite($this->pin, $value));
    }

    public function writeFraction(float $fraction): void
    {
        $this->write((int) round(max(0.0, min(1.0, $fraction)) * 255));
    }
}
