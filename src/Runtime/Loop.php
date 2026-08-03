<?php

declare(strict_types=1);

namespace Femus\Runtime;

interface Loop
{
    /** @param resource $stream @param callable(resource): void $onReadable */
    public function addReadStream($stream, callable $onReadable): void;

    /** @param resource $stream */
    public function removeReadStream($stream): void;

    /** @return string id таймера */
    public function addTimer(float $delaySeconds, callable $callback): string;

    /** @return string id таймера */
    public function addPeriodicTimer(float $intervalSeconds, callable $callback): string;

    public function cancelTimer(string $timerId): void;

    /** Крутится, пока не вызван stop() и пока есть таймеры или потоки. */
    public function run(): void;

    public function stop(): void;

    /** Одна итерация: ждёт события не дольше $timeoutSeconds, исполняет готовые таймеры. */
    public function tick(float $timeoutSeconds): void;
}
