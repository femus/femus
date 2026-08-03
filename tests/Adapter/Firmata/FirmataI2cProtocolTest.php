<?php

declare(strict_types=1);

use Femus\Adapter\Firmata\FirmataEncoder;
use Femus\Adapter\Firmata\FirmataParser;
use Femus\Adapter\Firmata\I2cReply;

it('кодирует i2c config', function () {
    expect(FirmataEncoder::i2cConfig())->toBe("\xF0\x78\x00\x00\xF7");
});

it('кодирует i2c write: адрес 0x27, один байт 0x9D', function () {
    // 0x9D > 0x7F → пара LSB=0x1D, MSB=0x01
    expect(FirmataEncoder::i2cWrite(0x27, "\x9D"))->toBe("\xF0\x76\x27\x00\x1D\x01\xF7");
});

it('кодирует i2c read: адрес 0x68, регистр 0x3B, 6 байт', function () {
    expect(FirmataEncoder::i2cReadRegister(0x68, 0x3B, 6))
        ->toBe("\xF0\x76\x68\x08\x3B\x00\x06\x00\xF7");
});

it('onSysex отдаёт payload между F0 и F7', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onSysex(function (string $payload) use (&$got) { $got = $payload; });
    $parser->push("\xF0\x79\x02\x05\xF7");
    expect($got)->toBe("\x79\x02\x05");
});

it('sysex, разрезанный между push, собирается целиком', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onSysex(function (string $payload) use (&$got) { $got = $payload; });
    $parser->push("\xF0\x77\x68");
    expect($got)->toBeNull();
    $parser->push("\x00\xF7");
    expect($got)->toBe("\x77\x68\x00");
});

it('декодирует i2c reply: адрес 0x68, регистр 0x3B, данные 0x40 0x00', function () {
    // payload: 77, addr LSB/MSB, reg LSB/MSB, данные парами
    $reply = I2cReply::fromSysexPayload("\x77\x68\x00\x3B\x00\x40\x00\x00\x00");
    expect($reply)->not->toBeNull()
        ->and($reply->address)->toBe(0x68)
        ->and($reply->register)->toBe(0x3B)
        ->and($reply->data)->toBe("\x40\x00");
});

it('декодирует данные reply с байтом больше 0x7F', function () {
    // байт 0x9D → пара 0x1D 0x01
    $reply = I2cReply::fromSysexPayload("\x77\x27\x00\x00\x00\x1D\x01");
    expect($reply->data)->toBe("\x9D");
});

it('fromSysexPayload возвращает null для не-I2C sysex', function () {
    expect(I2cReply::fromSysexPayload("\x79\x02\x05"))->toBeNull();
});
