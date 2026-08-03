<?php

declare(strict_types=1);

namespace Femus\Device;

use Femus\Contracts\DigitalPin;
use Femus\Runtime\Loop;

final class Button
{
    /** @var list<callable> */
    private array $pressListeners = [];

    /** @var list<callable> */
    private array $releaseListeners = [];

    private float $lastEdgeAt = -INF;

    public function __construct(
        private readonly DigitalPin $pin,
        private readonly Loop $loop,
        private readonly float $debounceSeconds = 0.02,
        private readonly bool $activeLow = true,
    ) {
        $pin->onChange(function (bool $high): void {
            $now = hrtime(true) / 1e9;
            if ($now - $this->lastEdgeAt < $this->debounceSeconds) {
                return;
            }
            $this->lastEdgeAt = $now;
            $pressed = $this->activeLow ? !$high : $high;
            foreach ($pressed ? $this->pressListeners : $this->releaseListeners as $listener) {
                $listener();
            }
        });
    }

    public function onPress(callable $fn): void
    {
        $this->pressListeners[] = $fn;
    }

    public function onRelease(callable $fn): void
    {
        $this->releaseListeners[] = $fn;
    }

    public function isPressed(): bool
    {
        return $this->activeLow ? !$this->pin->read() : $this->pin->read();
    }

    public function waitForPress(?float $timeoutSeconds = null): bool
    {
        $pressed = false;
        $this->pressListeners[] = function () use (&$pressed): void {
            $pressed = true;
        };
        $deadline = $timeoutSeconds === null ? null : hrtime(true) / 1e9 + $timeoutSeconds;
        while (!$pressed) {
            if ($deadline !== null && hrtime(true) / 1e9 >= $deadline) {
                return false;
            }
            $this->loop->tick(0.05);
        }

        return true;
    }
}
