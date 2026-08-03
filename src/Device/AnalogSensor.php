<?php

declare(strict_types=1);

namespace Femus\Device;

use Femus\Contracts\AnalogPin;
use Femus\Runtime\Loop;

final class AnalogSensor
{
    /** @var list<callable> */
    private array $listeners = [];

    private float $lastReported = 0.0;

    public function __construct(
        private readonly AnalogPin $pin,
        private readonly Loop $loop,
        private readonly float $threshold = 0.01,
    ) {
        $pin->onChange(function (float $value): void {
            if (abs($value - $this->lastReported) < $this->threshold) {
                return;
            }
            $this->lastReported = $value;
            foreach ($this->listeners as $listener) {
                $listener($value);
            }
        });
    }

    public function read(): float
    {
        return $this->pin->read();
    }

    public function readRaw(): int
    {
        return $this->pin->readRaw();
    }

    public function onChange(callable $fn): void
    {
        $this->listeners[] = $fn;
    }

    public function waitForValueAbove(float $level, ?float $timeoutSeconds = null): bool
    {
        $deadline = $timeoutSeconds === null ? null : hrtime(true) / 1e9 + $timeoutSeconds;
        while ($this->pin->read() < $level) {
            if ($deadline !== null && hrtime(true) / 1e9 >= $deadline) {
                return false;
            }
            $this->loop->tick(0.05);
        }

        return true;
    }
}
