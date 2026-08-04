<?php

declare(strict_types=1);

use Femus\Adapter\Firmata\FirmataEncoder;
use Femus\Adapter\Firmata\Hx711Reading;

it('encodes hx711 attach with pin pair', function () {
    expect(FirmataEncoder::hx711Attach(3, 2))->toBe("\xF0\x0E\x00\x03\x02\xF7");
});

it('decodes a positive reading', function () {
    // 100 → septets 0x64 00 00 00 00
    $reading = Hx711Reading::fromSysexPayload("\x0E\x01\x64\x00\x00\x00\x00");
    expect($reading)->not->toBeNull()->and($reading->value)->toBe(100);
});

it('decodes a multi-septet value', function () {
    // 16384 = 1 << 14 → septets 00 00 01 00 00
    $reading = Hx711Reading::fromSysexPayload("\x0E\x01\x00\x00\x01\x00\x00");
    expect($reading->value)->toBe(16384);
});

it('decodes a negative reading via two\'s complement', function () {
    // -1 = 0xFFFFFFFF → septets 7F 7F 7F 7F 0F
    $reading = Hx711Reading::fromSysexPayload("\x0E\x01\x7F\x7F\x7F\x7F\x0F");
    expect($reading->value)->toBe(-1);
});

it('returns null for foreign sysex payloads', function () {
    expect(Hx711Reading::fromSysexPayload("\x79\x02\x05"))->toBeNull()
        ->and(Hx711Reading::fromSysexPayload("\x0E\x00\x03\x02"))->toBeNull() // attach, not reading
        ->and(Hx711Reading::fromSysexPayload("\x0E\x01\x64"))->toBeNull();    // truncated
});
