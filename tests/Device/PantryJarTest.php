<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

function calibratedJar(FakeBoard $board, float $empty, float $full = 0.0, float $perServing = 0.0)
{
    // Calibrate the underlying cell to 1 raw unit = 1 gram.
    $jar = $board->pantryJar(3, 2, emptyGrams: $empty, fullGrams: $full, gramsPerServing: $perServing);
    $board->simulateScaleReading(3, 2, 0);
    $jar->scale()->tare();
    $board->simulateScaleReading(3, 2, 100);
    $jar->scale()->calibrate(100.0);

    return $jar;
}

it('reports contents as total minus the empty jar', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $jar = calibratedJar($board, empty: 200.0);        // empty jar weighs 200 g
    $board->simulateScaleReading(3, 2, 500);           // 500 g on the scale

    expect($jar->contentsGrams())->toEqualWithDelta(300.0, 0.01);
});

it('never reports negative contents', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $jar = calibratedJar($board, empty: 200.0);
    $board->simulateScaleReading(3, 2, 150);           // less than the empty jar (jar removed)

    expect($jar->contentsGrams())->toBe(0.0);
});

it('computes percent full against a known full amount', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $jar = calibratedJar($board, empty: 200.0, full: 500.0);
    $board->simulateScaleReading(3, 2, 500);           // 300 g of 500 g = 60%

    expect($jar->percentFull())->toEqualWithDelta(60.0, 0.01);
});

it('returns null percent when full amount is unknown', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $jar = calibratedJar($board, empty: 200.0);
    $board->simulateScaleReading(3, 2, 500);

    expect($jar->percentFull())->toBeNull();
});

it('counts whole servings left', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $jar = calibratedJar($board, empty: 200.0, perServing: 25.0);
    $board->simulateScaleReading(3, 2, 490);           // 290 g / 25 = 11.6 → 11

    expect($jar->servingsLeft())->toBe(11);
});

it('flags a low jar', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $jar = calibratedJar($board, empty: 200.0);
    $board->simulateScaleReading(3, 2, 240);           // 40 g left

    expect($jar->isLow(50.0))->toBeTrue()
        ->and($jar->isLow(30.0))->toBeFalse();
});

it('relays contents changes', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $jar = calibratedJar($board, empty: 200.0);
    $seen = [];
    $jar->onChange(function (float $g) use (&$seen) { $seen[] = $g; });

    $board->simulateScaleReading(3, 2, 400);           // 200 g contents
    expect($seen)->toBe([200.0]);
});
