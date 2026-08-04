<?php

declare(strict_types=1);

namespace Femus\Contracts;

interface ScaleInput
{
    /** @param callable(int): void $listener receives every raw ADC reading */
    public function onRawReading(callable $listener): void;

    public function lastRaw(): ?int;
}
