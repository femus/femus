<?php

declare(strict_types=1);

namespace Femus\Contracts;

interface PwmPin
{
    public function pin(): int;

    /** Set the duty cycle as a raw 0–255 value. */
    public function write(int $value): void;

    /** Set the duty cycle as a fraction 0.0–1.0 (e.g. LED brightness). */
    public function writeFraction(float $fraction): void;
}
