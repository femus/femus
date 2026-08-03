<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

it('read проксирует пин', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sensor = $board->analogSensor(0);
    $board->simulateAnalog(0, 512);
    expect($sensor->readRaw())->toBe(512)
        ->and($sensor->read())->toEqualWithDelta(0.5005, 0.001);
});

it('onChange фильтрует шум ниже порога', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sensor = $board->analogSensor(0, threshold: 0.05);
    $seen = [];
    $sensor->onChange(function (float $v) use (&$seen) { $seen[] = $v; });
    $board->simulateAnalog(0, 100);  // 0.098 — скачок от 0 больше порога → событие
    $board->simulateAnalog(0, 110);  // +0.0098 — меньше порога → тишина
    $board->simulateAnalog(0, 200);  // от последнего СООБЩЁННОГО (100) — больше порога → событие
    expect($seen)->toHaveCount(2);
});

it('waitForValueAbove ловит запланированное значение', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sensor = $board->analogSensor(1);
    $board->scheduleAnalog(0.02, 1, 900);
    expect($sensor->waitForValueAbove(0.8, timeoutSeconds: 1.0))->toBeTrue();
});

it('waitForValueAbove возвращает false по таймауту', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sensor = $board->analogSensor(1);
    expect($sensor->waitForValueAbove(0.8, timeoutSeconds: 0.05))->toBeFalse();
});
