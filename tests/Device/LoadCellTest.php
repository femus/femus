<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

it('tare zeroes and calibration converts raw to grams', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $cell = $board->loadCell(3, 2);

    $board->simulateScaleReading(3, 2, 8000);   // empty pan
    $cell->tare();
    $board->simulateScaleReading(3, 2, 24384);  // known 100 g on the pan
    $cell->calibrate(100.0);                    // scale = 163.84 raw/g
    $board->simulateScaleReading(3, 2, 40768);

    expect($cell->grams())->toEqualWithDelta(200.0, 0.01);
});

it('reports raw units before calibration', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $cell = $board->loadCell(3, 2);
    $board->simulateScaleReading(3, 2, 500);
    $cell->tare();
    $board->simulateScaleReading(3, 2, 750);
    expect($cell->grams())->toEqualWithDelta(250.0, 0.01)
        ->and($cell->raw())->toBe(750);
});

it('tare before any reading throws', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->loadCell(3, 2)->tare();
})->throws(LogicException::class);

it('calibrate rejects non-positive weight', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $cell = $board->loadCell(3, 2);
    $board->simulateScaleReading(3, 2, 100);
    $cell->calibrate(0.0);
})->throws(InvalidArgumentException::class);

it('onChange filters jitter below the threshold', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $cell = $board->loadCell(3, 2, thresholdGrams: 5.0);
    $board->simulateScaleReading(3, 2, 0);
    $cell->tare();
    $seen = [];
    $cell->onChange(function (float $g) use (&$seen) { $seen[] = $g; });
    $board->simulateScaleReading(3, 2, 10);  // +10 g (uncalibrated units) → event
    $board->simulateScaleReading(3, 2, 12);  // +2 from last reported → swallowed
    $board->simulateScaleReading(3, 2, 20);  // +10 from last reported → event
    expect($seen)->toHaveCount(2);
});

it('waitForWeightAbove sees a scheduled weight', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $cell = $board->loadCell(3, 2);
    $board->simulateScaleReading(3, 2, 0);
    $cell->tare();
    $board->scheduleScaleReading(0.02, 3, 2, 500);
    expect($cell->waitForWeightAbove(100.0, timeoutSeconds: 1.0))->toBeTrue();
});

it('waitForWeightAbove times out without readings', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $cell = $board->loadCell(3, 2);
    expect($cell->waitForWeightAbove(100.0, timeoutSeconds: 0.05))->toBeFalse();
});
