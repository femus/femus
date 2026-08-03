<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

/**
 * All bytes sent to the address as a single string.
 */
function lcdBytes(FakeBoard $board, int $address = 0x27): string
{
    $out = '';
    foreach ($board->fakeI2c()->writes as [$addr, $bytes]) {
        if ($addr === $address) {
            $out .= $bytes;
        }
    }

    return $out;
}

it('init sends 4-bit mode setup', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->lcd1602();
    // first step of init: command 0x33 (two nibble 0x3 sequences):
    // nibble 0x3, RS=0, BL=1: byte 0x38; pulse EN: 0x3C, 0x38
    expect(lcdBytes($board))->toContain("\x3C\x38");
});

it('write outputs character with RS set', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $lcd = $board->lcd1602();
    $board->fakeI2c()->writes = [];

    $lcd->write('A'); // 0x41: high nibble 0x4 → 0x49 (RS|BL), pulse 0x4D,0x49; low 0x1 → 0x19, pulse 0x1D,0x19

    expect(lcdBytes($board))->toBe("\x4D\x49\x1D\x19");
});

it('clear sends command 0x01 without RS', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $lcd = $board->lcd1602();
    $board->fakeI2c()->writes = [];

    $lcd->clear(); // 0x01: high nibble 0x0 → 0x08(BL), pulse 0x0C,0x08; low 0x1 → 0x18, pulse 0x1C,0x18

    expect(lcdBytes($board))->toBe("\x0C\x08\x1C\x18");
});

it('setCursor on second row uses 0x40 offset', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $lcd = $board->lcd1602();
    $board->fakeI2c()->writes = [];

    $lcd->setCursor(0, 1); // command 0x80|0x40 = 0xC0: nibbles 0xC and 0x0 → 0xC8 pulse, 0x08 pulse

    expect(lcdBytes($board))->toBe("\xCC\xC8\x0C\x08");
});

it('backlight(false) turns off backlight bit in subsequent commands', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $lcd = $board->lcd1602();
    $lcd->backlight(false);
    $board->fakeI2c()->writes = [];

    $lcd->clear(); // same bytes but without 0x08 bit

    expect(lcdBytes($board))->toBe("\x04\x00\x14\x10");
});
