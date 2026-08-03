<?php

declare(strict_types=1);

namespace Tests\Adapter\Fake;

use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

it('read нормализует 10-битное значение', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->analogPin(0);
    $board->simulateAnalog(0, 1023);
    expect($pin->readRaw())->toBe(1023)
        ->and($pin->read())->toEqualWithDelta(1.0, 0.001);
    $board->simulateAnalog(0, 512);
    expect($pin->read())->toEqualWithDelta(0.5005, 0.001);
});

it('onChange зовётся только при изменении значения', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->analogPin(2);
    $seen = [];
    $pin->onChange(function (float $v) use (&$seen) { $seen[] = $v; });
    $board->simulateAnalog(2, 100);
    $board->simulateAnalog(2, 100); // без изменения — не событие
    $board->simulateAnalog(2, 200);
    expect($seen)->toHaveCount(2);
});

it('scheduleAnalog инжектит значение через event loop', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->analogPin(1);
    $board->scheduleAnalog(0.01, 1, 300);
    $board->loop()->addTimer(0.05, fn () => $board->stop());
    $board->run();
    expect($pin->readRaw())->toBe(300);
});

it('канал возвращается и пин кэшируется', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->analogPin(3);
    expect($pin->channel())->toBe(3)
        ->and($board->analogPin(3))->toBe($pin);
});
