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

    /**
     * Blocking read of $length bytes with no register write first — for chips like the
     * TEA5767 that treat any written byte as a command.
     *
     * @throws I2cException on timeout
     */
    public function read(int $address, int $length): string;
}
