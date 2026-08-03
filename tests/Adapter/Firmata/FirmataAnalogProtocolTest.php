<?php

declare(strict_types=1);

use Femus\Adapter\Firmata\Firmata;
use Femus\Adapter\Firmata\FirmataEncoder;
use Femus\Adapter\Firmata\FirmataParser;

it('encodes enabling analog channel reporting', function () {
    expect(FirmataEncoder::reportAnalogChannel(0, true))->toBe("\xC0\x01")
        ->and(FirmataEncoder::reportAnalogChannel(5, false))->toBe("\xC5\x00");
});

it('parses analog message: channel 2, value 1023', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onAnalogMessage(function (int $ch, int $value) use (&$got) {
        $got = [$ch, $value];
    });
    $parser->push("\xE2\x7F\x07"); // 0x7F | (0x07 << 7) = 1023
    expect($got)->toBe([2, 1023]);
});

it('parses an analog message split across push calls', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onAnalogMessage(function (int $ch, int $value) use (&$got) {
        $got = [$ch, $value];
    });
    $parser->push("\xE0");
    $parser->push("\x40\x01"); // 0x40 | (0x01<<7) = 192
    expect($got)->toBe([0, 192]);
});

it('digital and analog messages in the same stream do not interfere', function () {
    $parser = new FirmataParser();
    $events = [];
    $parser->onDigitalMessage(function (int $port, int $mask) use (&$events) {
        $events[] = ['d', $port, $mask];
    });
    $parser->onAnalogMessage(function (int $ch, int $value) use (&$events) {
        $events[] = ['a', $ch, $value];
    });
    $parser->push("\x90\x04\x00" . "\xE1\x0A\x00");
    expect($events)->toBe([['d', 0, 4], ['a', 1, 10]]);
});
