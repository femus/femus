<?php

declare(strict_types=1);

use Femus\Runtime\StreamSelectLoop;

it('fires a one-shot timer exactly once', function () {
    $loop = new StreamSelectLoop();
    $calls = 0;
    $loop->addTimer(0.01, function () use (&$calls) { $calls++; });
    $loop->run();
    expect($calls)->toBe(1);
});

it('fires a periodic timer until cancelled', function () {
    $loop = new StreamSelectLoop();
    $calls = 0;
    $id = null;
    $id = $loop->addPeriodicTimer(0.005, function () use (&$calls, $loop, &$id) {
        if (++$calls >= 3) {
            $loop->cancelTimer($id);
        }
    });
    $loop->run();
    expect($calls)->toBe(3);
});

it('stops on stop() called from within a callback', function () {
    $loop = new StreamSelectLoop();
    $loop->addPeriodicTimer(0.001, fn () => $loop->stop());
    $loop->run(); // must not hang
    expect(true)->toBeTrue();
});

it('reads data from a stream', function () {
    $loop = new StreamSelectLoop();
    [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    stream_set_blocking($a, false);
    $received = '';
    $loop->addReadStream($a, function ($stream) use (&$received, $loop, $a) {
        $received .= (string) stream_get_contents($stream);
        $loop->removeReadStream($a);
    });
    fwrite($b, 'ping');
    $loop->run();
    expect($received)->toBe('ping');
});

it('fires a timer with timeout > 1s correctly', function () {
    $loop = new StreamSelectLoop();
    $fired = false;
    $start = microtime(true);
    $loop->addTimer(0.01, function () use (&$fired) { $fired = true; });
    $loop->tick(1.5);
    $elapsed = microtime(true) - $start;
    expect($fired)->toBeTrue();
    expect($elapsed)->toBeLessThan(1.0);
});

it('a callback that ticks the loop itself does not fire its own timer again', function () {
    // the shape of a blocking I2C read inside a timer: the read spins the loop until the reply
    $loop = new StreamSelectLoop();
    $calls = 0;
    $loop->addTimer(0, function () use ($loop, &$calls) {
        $calls++;
        if ($calls < 5) {
            $loop->tick(0);
        }
        $loop->stop();
    });

    $loop->run();

    expect($calls)->toBe(1);
});
