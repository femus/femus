<?php

declare(strict_types=1);

use Femus\Runtime\StreamSelectLoop;

it('вызывает одноразовый таймер ровно один раз', function () {
    $loop = new StreamSelectLoop();
    $calls = 0;
    $loop->addTimer(0.01, function () use (&$calls) { $calls++; });
    $loop->run();
    expect($calls)->toBe(1);
});

it('вызывает периодический таймер до отмены', function () {
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

it('останавливается по stop() изнутри колбэка', function () {
    $loop = new StreamSelectLoop();
    $loop->addPeriodicTimer(0.001, fn () => $loop->stop());
    $loop->run(); // не должен зависнуть
    expect(true)->toBeTrue();
});

it('читает данные из потока', function () {
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
