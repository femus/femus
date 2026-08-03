<?php

declare(strict_types=1);

namespace Femus\Contracts;

interface DigitalPin
{
    public function number(): int;

    public function write(bool $high): void;

    public function read(): bool;

    /** @param callable(bool): void $listener получает новое состояние пина */
    public function onChange(callable $listener): void;
}
