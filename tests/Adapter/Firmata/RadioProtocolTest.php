<?php

declare(strict_types=1);

use Femus\Adapter\Firmata\FirmataEncoder;
use Femus\Adapter\Firmata\RadioMessageFrame;

it('encodes radio attach', function () {
    expect(FirmataEncoder::radioAttach(1, 11, 12))->toBe("\xF0\x0D\x00\x01\x0B\x0C\xF7");
});

it('encodes radio send with 7-bit pairs', function () {
    // 'H' = 0x48 → 48 00, 'i' = 0x69 → 69 00
    expect(FirmataEncoder::radioSend(3, 'Hi'))->toBe("\xF0\x0D\x01\x03\x48\x00\x69\x00\xF7");
});

it('encodes bytes above 0x7F in send', function () {
    // 0xC3 → 43 01 (UTF-8 continuation bytes survive the link)
    expect(FirmataEncoder::radioSend(1, "\xC3"))->toBe("\xF0\x0D\x01\x01\x43\x01\xF7");
});

it('decodes a received frame', function () {
    $frame = RadioMessageFrame::fromSysexPayload("\x0D\x02\x02\x01\x48\x00\x69\x00");
    expect($frame)->not->toBeNull()
        ->and($frame->from)->toBe(2)
        ->and($frame->to)->toBe(1)
        ->and($frame->message)->toBe('Hi');
});

it('returns null for foreign payloads', function () {
    expect(RadioMessageFrame::fromSysexPayload("\x0E\x01\x64\x00\x00\x00\x00"))->toBeNull()
        ->and(RadioMessageFrame::fromSysexPayload("\x0D\x00\x01\x0B\x0C"))->toBeNull()
        ->and(RadioMessageFrame::fromSysexPayload("\x0D\x02\x02"))->toBeNull();
});
