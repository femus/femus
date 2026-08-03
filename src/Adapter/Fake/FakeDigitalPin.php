<?php

declare(strict_types=1);

namespace Femus\Adapter\Fake;

use Femus\Contracts\DigitalPin;
use Femus\Contracts\PinMode;

final class FakeDigitalPin implements DigitalPin
{
    private bool $state = false;

    /** @var list<callable> */
    private array $listeners = [];

    public function __construct(
        private readonly int $number,
        public readonly PinMode $mode,
    ) {
    }

    public function number(): int
    {
        return $this->number;
    }

    public function write(bool $high): void
    {
        $this->state = $high;
    }

    public function read(): bool
    {
        return $this->state;
    }

    public function onChange(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    /** Тестовый вход: имитация внешнего сигнала на пине. */
    public function simulate(bool $high): void
    {
        if ($high === $this->state) {
            return;
        }
        $this->state = $high;
        foreach ($this->listeners as $listener) {
            $listener($high);
        }
    }
}
