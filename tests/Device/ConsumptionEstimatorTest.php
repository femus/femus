<?php

declare(strict_types=1);

use Femus\Device\ConsumptionEstimator;

const DAY = 86400.0;

it('has no rate before two samples', function () {
    $e = new ConsumptionEstimator();
    expect($e->gramsPerDay())->toBeNull();

    $e->record(0, 700);
    expect($e->gramsPerDay())->toBeNull();
});

it('estimates a steady consumption rate', function () {
    $e = new ConsumptionEstimator();
    $e->record(0 * DAY, 700);
    $e->record(1 * DAY, 650);
    $e->record(2 * DAY, 600);          // 50 g/day downhill

    expect($e->gramsPerDay())->toEqualWithDelta(50.0, 0.01);
});

it('projects days left from the current weight', function () {
    $e = new ConsumptionEstimator();
    $e->record(0 * DAY, 700);
    $e->record(1 * DAY, 650);
    $e->record(2 * DAY, 600);

    expect($e->daysLeft(600))->toEqualWithDelta(12.0, 0.01);  // 600 g / 50 g per day
});

it('fits a rate through noisy samples', function () {
    $e = new ConsumptionEstimator();
    $e->record(0 * DAY, 500);
    $e->record(1 * DAY, 462);          // wobble up/down around a 40 g/day trend
    $e->record(2 * DAY, 418);
    $e->record(3 * DAY, 380);

    expect($e->gramsPerDay())->toEqualWithDelta(40.0, 1.0);
});

it('returns null when the jar is refilled (weight grows)', function () {
    $e = new ConsumptionEstimator();
    $e->record(0 * DAY, 100);
    $e->record(1 * DAY, 200);          // went up → not consuming

    expect($e->gramsPerDay())->toBeNull()
        ->and($e->daysLeft(200))->toBeNull();
});

it('returns null days left when weight is steady', function () {
    $e = new ConsumptionEstimator();
    $e->record(0 * DAY, 300);
    $e->record(1 * DAY, 300);

    expect($e->daysLeft(300))->toBeNull();
});
