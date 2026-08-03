<?php

declare(strict_types=1);

use Femus\Adapter\Firmata\FirmataBoard;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\InMemoryTransport;

function readyAnalogBoard(InMemoryTransport $transport): FirmataBoard
{
    $board = new FirmataBoard($transport, new StreamSelectLoop());
    $transport->feed("\xF9\x02\x05");
    $board->awaitReady();

    return $board;
}

it('запрос аналогового пина включает репортинг канала', function () {
    $transport = new InMemoryTransport();
    $board = readyAnalogBoard($transport);
    $transport->written = '';

    $board->analogPin(3);

    expect($transport->written)->toBe("\xC3\x01");
});

it('входящее analog message обновляет пин и зовёт onChange', function () {
    $transport = new InMemoryTransport();
    $board = readyAnalogBoard($transport);
    $pin = $board->analogPin(0);

    $seen = null;
    $pin->onChange(function (float $v) use (&$seen, $board) {
        $seen = $v;
        $board->stop();
    });

    $transport->feed("\xE0\x7F\x07"); // 1023
    $board->loop()->addTimer(1.0, fn () => $board->stop());
    $board->run();

    expect($pin->readRaw())->toBe(1023)
        ->and($seen)->toEqualWithDelta(1.0, 0.001);
});

it('сообщение чужого канала не трогает пин', function () {
    $transport = new InMemoryTransport();
    $board = readyAnalogBoard($transport);
    $pin = $board->analogPin(0);

    $transport->feed("\xE5\x0A\x00"); // канал 5
    $board->loop()->addTimer(0.05, fn () => $board->stop());
    $board->run();

    expect($pin->readRaw())->toBe(0);
});
