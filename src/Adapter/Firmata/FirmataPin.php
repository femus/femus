<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

use Femus\Contracts\DigitalPin;
use Femus\Contracts\PinMode;

final class FirmataPin implements DigitalPin
{
    private bool $state = false;

    /** @var list<callable> */
    private array $listeners = [];

    public function __construct(
        private readonly int $number,
        public readonly PinMode $mode,
        private readonly FirmataBoard $board,
    ) {
    }

    public function number(): int
    {
        return $this->number;
    }

    /** Writes the state of an output pin; onChange listeners are intentionally not called. */
    public function write(bool $high): void
    {
        $this->board->writeDigital($this->number, $high);
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

    /** @internal called by FirmataBoard on an incoming digital message */
    public function updateFromBoard(bool $high): void
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
