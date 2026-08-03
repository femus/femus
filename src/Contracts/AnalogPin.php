<?php

declare(strict_types=1);

namespace Femus\Contracts;

interface AnalogPin
{
    public function channel(): int;

    /** Normalised value 0.0–1.0. */
    public function read(): float;

    /** Raw 10-bit value 0–1023. */
    public function readRaw(): int;

    /** @param callable(float): void $listener receives the normalised value */
    public function onChange(callable $listener): void;
}
