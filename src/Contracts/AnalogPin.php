<?php

declare(strict_types=1);

namespace Femus\Contracts;

interface AnalogPin
{
    public function channel(): int;

    /** Нормализованное значение 0.0–1.0. */
    public function read(): float;

    /** Сырое 10-битное значение 0–1023. */
    public function readRaw(): int;

    /** @param callable(float): void $listener получает нормализованное значение */
    public function onChange(callable $listener): void;
}
