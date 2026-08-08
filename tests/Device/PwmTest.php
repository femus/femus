<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Adapter\Firmata\FirmataEncoder;
use Femus\Runtime\StreamSelectLoop;

it('encodes analogWrite as an ANALOG_MESSAGE 7-bit pair', function () {
    // pin 6, value 200 → 0xE6, 200&0x7F=72, 200>>7=1
    expect(FirmataEncoder::analogWrite(6, 200))->toBe(chr(0xE6) . chr(72) . chr(1));
});

it('writes and clamps a PWM duty via the fake board', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->pwmPin(6);

    $pin->write(128);
    expect($board->fakePwmPin(6)->value)->toBe(128);

    $pin->write(999);
    expect($board->fakePwmPin(6)->value)->toBe(255);
});

it('maps a fraction to 0–255', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->pwmPin(9)->writeFraction(0.5);
    expect($board->fakePwmPin(9)->value)->toBe(128);
});

it('drives an RGB LED from a hex color', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $rgb = $board->rgbLed(9, 10, 11);

    $rgb->hex('#ff8800');
    expect($board->fakePwmPin(9)->value)->toBe(255)
        ->and($board->fakePwmPin(10)->value)->toBe(136)
        ->and($board->fakePwmPin(11)->value)->toBe(0);

    $rgb->off();
    expect($board->fakePwmPin(9)->value)->toBe(0);
});
