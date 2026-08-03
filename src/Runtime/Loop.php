<?php

declare(strict_types=1);

namespace Femus\Runtime;

interface Loop
{
    /** @param resource $stream @param callable(resource): void $onReadable */
    public function addReadStream($stream, callable $onReadable): void;

    /** @param resource $stream */
    public function removeReadStream($stream): void;

    /** @return string timer id */
    public function addTimer(float $delaySeconds, callable $callback): string;

    /** @return string timer id */
    public function addPeriodicTimer(float $intervalSeconds, callable $callback): string;

    public function cancelTimer(string $timerId): void;

    /** Runs until stop() is called and there are no remaining timers or streams. */
    public function run(): void;

    public function stop(): void;

    /** Single iteration: waits up to $timeoutSeconds, fires due timers; timers fire even after stop(). */
    public function tick(float $timeoutSeconds): void;
}
