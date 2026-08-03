<?php

declare(strict_types=1);

namespace Femus\Adapter\Fake;

use Femus\Contracts\I2cBus;
use Femus\Contracts\I2cException;

final class FakeI2cBus implements I2cBus
{
    /** @var list<array{0: int, 1: string}> */
    public array $writes = [];

    /** @var list<string> */
    private array $readQueue = [];

    public function write(int $address, string $bytes): void
    {
        $this->writes[] = [$address, $bytes];
    }

    public function readRegister(int $address, int $register, int $length): string
    {
        if ($this->readQueue === []) {
            throw new I2cException(
                sprintf('No queued read response for 0x%02X/0x%02X', $address, $register),
            );
        }

        return array_shift($this->readQueue);
    }

    public function queueRead(string $bytes): void
    {
        $this->readQueue[] = $bytes;
    }
}
