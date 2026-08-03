<?php

declare(strict_types=1);

namespace Femus\Device;

use Femus\Contracts\DigitalPin;
use Femus\Runtime\Loop;

final class MotionSensor
{
    /** @var list<callable> */
    private array $motionListeners = [];

    /** @var list<callable> */
    private array $idleListeners = [];

    public function __construct(
        private readonly DigitalPin $pin,
        private readonly Loop $loop,
    ) {
        $pin->onChange(function (bool $high): void {
            foreach ($high ? $this->motionListeners : $this->idleListeners as $listener) {
                $listener();
            }
        });
    }

    public function onMotion(callable $fn): void
    {
        $this->motionListeners[] = $fn;
    }

    public function onIdle(callable $fn): void
    {
        $this->idleListeners[] = $fn;
    }

    public function isActive(): bool
    {
        return $this->pin->read();
    }

    public function waitForMotion(?float $timeoutSeconds = null): bool
    {
        $detected = false;
        $listener = function () use (&$detected): void {
            $detected = true;
        };
        $this->motionListeners[] = $listener;
        $deadline = $timeoutSeconds === null ? null : hrtime(true) / 1e9 + $timeoutSeconds;
        try {
            while (!$detected) {
                if ($deadline !== null && hrtime(true) / 1e9 >= $deadline) {
                    return false;
                }
                $this->loop->tick(0.05);
            }

            return true;
        } finally {
            $index = array_search($listener, $this->motionListeners, true);
            if ($index !== false) {
                unset($this->motionListeners[$index]);
                $this->motionListeners = array_values($this->motionListeners);
            }
        }
    }
}
