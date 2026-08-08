<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// Autonomous node heartbeat: blink the onboard LED and log that we're alive.
// Designed to run under systemd on a headless board (see deploy/femus-node.service).
//
// Usage: php examples/heartbeat.php [port]   (port defaults to autodetect)

$board = Board::firmata($argv[1] ?? null);
$led = $board->led(13);

$beats = 0;
$board->loop()->addPeriodicTimer(1.0, function () use ($led, &$beats): void {
    $led->toggle();
    $beats++;
    if ($beats % 10 === 0) {
        fwrite(STDOUT, sprintf("[%s] alive — %d beats\n", date('c'), $beats));
    }
});

fwrite(STDOUT, "femus node heartbeat started\n");
$board->run();
