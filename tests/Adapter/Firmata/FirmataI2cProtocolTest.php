<?php

declare(strict_types=1);

use Femus\Adapter\Firmata\FirmataEncoder;
use Femus\Adapter\Firmata\FirmataParser;
use Femus\Adapter\Firmata\I2cReply;

it('encodes i2c config', function () {
    expect(FirmataEncoder::i2cConfig())->toBe("\xF0\x78\x00\x00\xF7");
});

it('encodes i2c write: address 0x27, one byte 0x9D', function () {
    // 0x9D > 0x7F → pair LSB=0x1D, MSB=0x01
    expect(FirmataEncoder::i2cWrite(0x27, "\x9D"))->toBe("\xF0\x76\x27\x00\x1D\x01\xF7");
});

it('encodes i2c read: address 0x68, register 0x3B, 6 bytes', function () {
    expect(FirmataEncoder::i2cReadRegister(0x68, 0x3B, 6))
        ->toBe("\xF0\x76\x68\x08\x3B\x00\x06\x00\xF7");
});

it('onSysex delivers the payload between F0 and F7', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onSysex(function (string $payload) use (&$got) { $got = $payload; });
    $parser->push("\xF0\x79\x02\x05\xF7");
    expect($got)->toBe("\x79\x02\x05");
});

it('sysex split across push calls is assembled correctly', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onSysex(function (string $payload) use (&$got) { $got = $payload; });
    $parser->push("\xF0\x77\x68");
    expect($got)->toBeNull();
    $parser->push("\x00\xF7");
    expect($got)->toBe("\x77\x68\x00");
});

it('decodes i2c reply: address 0x68, register 0x3B, data 0x40 0x00', function () {
    // payload: 77, addr LSB/MSB, reg LSB/MSB, data pairs
    $reply = I2cReply::fromSysexPayload("\x77\x68\x00\x3B\x00\x40\x00\x00\x00");
    expect($reply)->not->toBeNull()
        ->and($reply->address)->toBe(0x68)
        ->and($reply->register)->toBe(0x3B)
        ->and($reply->data)->toBe("\x40\x00");
});

it('decodes reply data with a byte above 0x7F', function () {
    // byte 0x9D → pair 0x1D 0x01
    $reply = I2cReply::fromSysexPayload("\x77\x27\x00\x00\x00\x1D\x01");
    expect($reply->data)->toBe("\x9D");
});

it('fromSysexPayload returns null for non-I2C sysex', function () {
    expect(I2cReply::fromSysexPayload("\x79\x02\x05"))->toBeNull();
});
