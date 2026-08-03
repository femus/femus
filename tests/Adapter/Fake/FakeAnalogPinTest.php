<?php

declare(strict_types=1);

namespace Tests\Adapter\Fake;

use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

it('read normalises a 10-bit value', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->analogPin(0);
    $board->simulateAnalog(0, 1023);
    expect($pin->readRaw())->toBe(1023)
        ->and($pin->read())->toEqualWithDelta(1.0, 0.001);
    $board->simulateAnalog(0, 512);
    expect($pin->read())->toEqualWithDelta(0.5005, 0.001);
});

it('onChange is called only when the value changes', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->analogPin(2);
    $seen = [];
    $pin->onChange(function (float $v) use (&$seen) { $seen[] = $v; });
    $board->simulateAnalog(2, 100);
    $board->simulateAnalog(2, 100); // no change — not an event
    $board->simulateAnalog(2, 200);
    expect($seen)->toHaveCount(2);
});

it('scheduleAnalog injects a value via the event loop', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->analogPin(1);
    $board->scheduleAnalog(0.01, 1, 300);
    $board->loop()->addTimer(0.05, fn () => $board->stop());
    $board->run();
    expect($pin->readRaw())->toBe(300);
});

it('channel is returned and the pin is cached', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->analogPin(3);
    expect($pin->channel())->toBe(3)
        ->and($board->analogPin(3))->toBe($pin);
});
