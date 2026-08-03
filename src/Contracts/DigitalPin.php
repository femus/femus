<?php

declare(strict_types=1);

namespace Femus\Contracts;

interface DigitalPin
{
    public function number(): int;

    public function write(bool $high): void;

    public function read(): bool;

    /** @param callable(bool): void $listener receives the new pin state */
    public function onChange(callable $listener): void;
}
