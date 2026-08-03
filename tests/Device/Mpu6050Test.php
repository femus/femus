<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

it('constructor wakes the chip via power register write', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->mpu6050();
    expect($board->fakeI2c()->writes)->toBe([[0x68, "\x6B\x00"]]);
});

it('readAccel decodes int16 and scales to g', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $mpu = $board->mpu6050();
    // X=+16384 (1g), Y=-16384 (-1g), Z=0
    $board->fakeI2c()->queueRead("\x40\x00\xC0\x00\x00\x00");
    $accel = $mpu->readAccel();
    expect($accel['x'])->toEqualWithDelta(1.0, 0.001)
        ->and($accel['y'])->toEqualWithDelta(-1.0, 0.001)
        ->and($accel['z'])->toEqualWithDelta(0.0, 0.001);
});

it('readGyro scales to degrees per second', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $mpu = $board->mpu6050();
    // X=+131 → 1.0 °/s
    $board->fakeI2c()->queueRead("\x00\x83\x00\x00\x00\x00");
    expect($mpu->readGyro()['x'])->toEqualWithDelta(1.0, 0.01);
});

it('readTemperature converts to celsius', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $mpu = $board->mpu6050();
    // raw 0 → 36.53 °C
    $board->fakeI2c()->queueRead("\x00\x00");
    expect($mpu->readTemperature())->toEqualWithDelta(36.53, 0.01);
});
