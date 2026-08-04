<?php

declare(strict_types=1);

namespace Femus\Adapter\Fake;

use Femus\Contracts\ScaleInput;

final class FakeScaleInput implements ScaleInput
{
    private ?int $lastRaw = null;

    /** @var list<callable> */
    private array $listeners = [];

    public function onRawReading(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function lastRaw(): ?int
    {
        return $this->lastRaw;
    }

    /** Test input: every simulated sample is delivered, duplicates included. */
    public function simulate(int $raw): void
    {
        $this->lastRaw = $raw;
        foreach ($this->listeners as $listener) {
            $listener($raw);
        }
    }
}
