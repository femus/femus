<?php

declare(strict_types=1);

use Femus\Adapter\Firmata\Firmata;
use Femus\Adapter\Firmata\FirmataEncoder;
use Femus\Adapter\Firmata\FirmataParser;

it('encodes setPinMode', function () {
    expect(FirmataEncoder::setPinMode(13, Firmata::MODE_OUTPUT))->toBe("\xF4\x0D\x01");
});

it('encodes digitalWrite: pin 13 = port 1, bit 5', function () {
    expect(FirmataEncoder::digitalWrite(1, 0b00100000))->toBe("\x91\x20\x00");
});

it('encodes digitalWrite with bit 7 in the high byte', function () {
    expect(FirmataEncoder::digitalWrite(0, 0b10000001))->toBe("\x90\x01\x01");
});

it('encodes enabling digital port reporting', function () {
    expect(FirmataEncoder::reportDigitalPort(0, true))->toBe("\xD0\x01");
});

it('parses a digital message', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onDigitalMessage(function (int $port, int $bitmask) use (&$got) {
        $got = [$port, $bitmask];
    });
    $parser->push("\x90\x04\x00"); // port 0, pin 2 HIGH
    expect($got)->toBe([0, 4]);
});

it('parses a message split across push calls', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onDigitalMessage(function (int $port, int $bitmask) use (&$got) {
        $got = [$port, $bitmask];
    });
    $parser->push("\x90\x04");
    expect($got)->toBeNull();
    $parser->push("\x00");
    expect($got)->toBe([0, 4]);
});

it('parses the protocol version', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onVersion(function (int $major, int $minor) use (&$got) {
        $got = "$major.$minor";
    });
    $parser->push("\xF9\x02\x05");
    expect($got)->toBe('2.5');
});

it('skips sysex blocks and garbage without losing subsequent messages', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onDigitalMessage(function (int $port, int $bitmask) use (&$got) {
        $got = [$port, $bitmask];
    });
    // sysex (e.g. firmware report on start) + garbage byte + our message
    $parser->push("\xF0\x79\x02\x05\xF7" . "\x42" . "\x90\x04\x00");
    expect($got)->toBe([0, 4]);
});
