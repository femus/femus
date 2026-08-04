<?php

declare(strict_types=1);

namespace Femus\Contracts;

interface RadioLink
{
    public const BROADCAST = 127;

    public function send(int $toAddress, string $message): void;

    /** @param callable(RadioMessage): void $listener */
    public function onMessage(callable $listener): void;

    public function address(): int;
}
