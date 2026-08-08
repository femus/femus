<?php

declare(strict_types=1);

namespace Femus\Adapter\Fake;

use Femus\Contracts\PwmPin;

final class FakePwmPin implements PwmPin
{
    public int $value = 0;

    public function __construct(private readonly int $pin)
    {
    }

    public function pin(): int
    {
        return $this->pin;
    }

    public function write(int $value): void
    {
        $this->value = max(0, min(255, $value));
    }

    public function writeFraction(float $fraction): void
    {
        $this->write((int) round(max(0.0, min(1.0, $fraction)) * 255));
    }
}
