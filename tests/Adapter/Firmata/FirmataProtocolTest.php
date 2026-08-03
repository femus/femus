<?php

declare(strict_types=1);

use Femus\Adapter\Firmata\Firmata;
use Femus\Adapter\Firmata\FirmataEncoder;
use Femus\Adapter\Firmata\FirmataParser;

it('кодирует setPinMode', function () {
    expect(FirmataEncoder::setPinMode(13, Firmata::MODE_OUTPUT))->toBe("\xF4\x0D\x01");
});

it('кодирует digitalWrite: pin 13 = порт 1, бит 5', function () {
    expect(FirmataEncoder::digitalWrite(1, 0b00100000))->toBe("\x91\x20\x00");
});

it('кодирует digitalWrite с битом 7 в старшем байте', function () {
    expect(FirmataEncoder::digitalWrite(0, 0b10000001))->toBe("\x90\x01\x01");
});

it('кодирует включение репортинга порта', function () {
    expect(FirmataEncoder::reportDigitalPort(0, true))->toBe("\xD0\x01");
});

it('парсит digital message', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onDigitalMessage(function (int $port, int $bitmask) use (&$got) {
        $got = [$port, $bitmask];
    });
    $parser->push("\x90\x04\x00"); // порт 0, пин 2 HIGH
    expect($got)->toBe([0, 4]);
});

it('парсит сообщение, разрезанное между push', function () {
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

it('парсит версию протокола', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onVersion(function (int $major, int $minor) use (&$got) {
        $got = "$major.$minor";
    });
    $parser->push("\xF9\x02\x05");
    expect($got)->toBe('2.5');
});

it('пропускает sysex-блоки и мусор, не теряя следующие сообщения', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onDigitalMessage(function (int $port, int $bitmask) use (&$got) {
        $got = [$port, $bitmask];
    });
    // sysex (например, отчёт о прошивке при старте) + мусорный байт + наше сообщение
    $parser->push("\xF0\x79\x02\x05\xF7" . "\x42" . "\x90\x04\x00");
    expect($got)->toBe([0, 4]);
});
