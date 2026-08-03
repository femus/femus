<?php

declare(strict_types=1);

namespace Femus\Contracts;

interface I2cBus
{
    public function write(int $address, string $bytes): void;

    /**
     * Blocking read of $length bytes from a register.
     *
     * @throws I2cException on timeout
     */
    public function readRegister(int $address, int $register, int $length): string;
}
