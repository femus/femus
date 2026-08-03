<?php

declare(strict_types=1);

namespace Femus\Runtime;

final class StreamSelectLoop implements Loop
{
    /** @var array<int, resource> */
    private array $readStreams = [];

    /** @var array<int, callable> */
    private array $readListeners = [];

    /** @var array<string, array{at: float, interval: ?float, cb: callable}> */
    private array $timers = [];

    private int $nextTimerId = 1;

    private bool $stopped = false;

    public function addReadStream($stream, callable $onReadable): void
    {
        $this->readStreams[(int) $stream] = $stream;
        $this->readListeners[(int) $stream] = $onReadable;
    }

    public function removeReadStream($stream): void
    {
        unset($this->readStreams[(int) $stream], $this->readListeners[(int) $stream]);
    }

    public function addTimer(float $delaySeconds, callable $callback): string
    {
        return $this->schedule($delaySeconds, null, $callback);
    }

    public function addPeriodicTimer(float $intervalSeconds, callable $callback): string
    {
        return $this->schedule($intervalSeconds, $intervalSeconds, $callback);
    }

    public function cancelTimer(string $timerId): void
    {
        unset($this->timers[$timerId]);
    }

    public function run(): void
    {
        $this->stopped = false;
        while (!$this->stopped && ($this->timers !== [] || $this->readStreams !== [])) {
            $this->tick(0.05);
        }
    }

    public function stop(): void
    {
        $this->stopped = true;
    }

    public function tick(float $timeoutSeconds): void
    {
        $timeout = max(0.0, min($timeoutSeconds, $this->timeUntilNextTimer() ?? $timeoutSeconds));

        if ($this->readStreams !== []) {
            $read = array_values($this->readStreams);
            $write = null;
            $except = null;
            $tvSec = (int) $timeout;
            $tvUsec = (int) round(($timeout - $tvSec) * 1_000_000);
            $changed = @stream_select($read, $write, $except, $tvSec, $tvUsec);
            if ($changed !== false && $changed > 0) {
                foreach ($read as $stream) {
                    $listener = $this->readListeners[(int) $stream] ?? null;
                    if ($listener !== null) {
                        $listener($stream);
                    }
                }
            }
        } elseif ($timeout > 0) {
            $tvSec = (int) $timeout;
            if ($tvSec > 0) {
                sleep($tvSec);
            }
            usleep((int) round(($timeout - $tvSec) * 1_000_000));
        }

        $this->fireDueTimers();
    }

    private function schedule(float $delay, ?float $interval, callable $cb): string
    {
        $id = 't' . $this->nextTimerId++;
        $this->timers[$id] = ['at' => $this->now() + $delay, 'interval' => $interval, 'cb' => $cb];

        return $id;
    }

    private function now(): float
    {
        return hrtime(true) / 1e9;
    }

    private function timeUntilNextTimer(): ?float
    {
        if ($this->timers === []) {
            return null;
        }
        $next = min(array_column($this->timers, 'at'));

        return max(0.0, $next - $this->now());
    }

    private function fireDueTimers(): void
    {
        $now = $this->now();
        foreach ($this->timers as $id => $timer) {
            if ($timer['at'] > $now) {
                continue;
            }
            ($timer['cb'])();
            if (!isset($this->timers[$id])) {
                continue; // отменён внутри колбэка
            }
            if ($timer['interval'] === null) {
                unset($this->timers[$id]);
            } else {
                $this->timers[$id]['at'] = $now + $timer['interval'];
            }
        }
    }
}
