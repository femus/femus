<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

it('read proxies the pin', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sensor = $board->analogSensor(0);
    $board->simulateAnalog(0, 512);
    expect($sensor->readRaw())->toBe(512)
        ->and($sensor->read())->toEqualWithDelta(0.5005, 0.001);
});

it('onChange filters noise below the threshold', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sensor = $board->analogSensor(0, threshold: 0.05);
    $seen = [];
    $sensor->onChange(function (float $v) use (&$seen) { $seen[] = $v; });
    $board->simulateAnalog(0, 100);  // 0.098 — jump from 0 exceeds threshold → event
    $board->simulateAnalog(0, 110);  // +0.0098 — below threshold → silence
    $board->simulateAnalog(0, 200);  // from last REPORTED value (100) — exceeds threshold → event
    expect($seen)->toHaveCount(2);
});

it('waitForValueAbove catches a scheduled value', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sensor = $board->analogSensor(1);
    $board->scheduleAnalog(0.02, 1, 900);
    expect($sensor->waitForValueAbove(0.8, timeoutSeconds: 1.0))->toBeTrue();
});

it('waitForValueAbove returns false on timeout', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sensor = $board->analogSensor(1);
    expect($sensor->waitForValueAbove(0.8, timeoutSeconds: 0.05))->toBeFalse();
});
