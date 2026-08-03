<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

use Femus\Contracts\AnalogPin;

final class FirmataAnalogPin implements AnalogPin
{
    private int $raw = 0;

    /** @var list<callable> */
    private array $listeners = [];

    public function __construct(private readonly int $channel)
    {
    }

    public function channel(): int
    {
        return $this->channel;
    }

    public function read(): float
    {
        return $this->raw / 1023.0;
    }

    public function readRaw(): int
    {
        return $this->raw;
    }

    public function onChange(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    /** @internal called by FirmataBoard on an incoming analog message */
    public function updateFromBoard(int $raw): void
    {
        if ($raw === $this->raw) {
            return;
        }
        $this->raw = $raw;
        foreach ($this->listeners as $listener) {
            $listener($this->read());
        }
    }
}
