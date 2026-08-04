<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

// Pin mapping: rs=7, en=8, d4=9, d5=10, d6=11, d7=12

it('init sends 6 commands resulting in 24 EN pulses', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->lcd1602Parallel(7, 8, 9, 10, 11, 12);

    // 6 init commands × 2 nibbles per command × 2 EN writes (true + false) = 24
    expect(count($board->pin(8)->writes))->toBe(24);
});

it('writing A sends correct nibble signals on RS, d4, d6, and EN', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $lcd = $board->lcd1602Parallel(7, 8, 9, 10, 11, 12);

    // Reset write histories on all six pins after init
    foreach ([7, 8, 9, 10, 11, 12] as $pin) {
        $board->pin($pin)->writes = [];
    }

    $lcd->write('A'); // 0x41: high nibble 0x4, low nibble 0x1

    // RS must be true for both nibbles (data mode, not command)
    expect($board->pin(7)->writes)->toBe([true, true]);

    // d6: high nibble 0x4 → bit2 set (0x4 & 0x4 = true); low nibble 0x1 → bit2 clear (0x1 & 0x4 = false)
    expect($board->pin(11)->writes)->toBe([true, false]);

    // d4: high nibble 0x4 → bit0 clear (0x4 & 0x1 = false); low nibble 0x1 → bit0 set (0x1 & 0x1 = true)
    expect($board->pin(9)->writes)->toBe([false, true]);

    // EN pulses: true then false per nibble, two nibbles total
    expect($board->pin(8)->writes)->toBe([true, false, true, false]);
});

it('clear sends command 0x01 with RS low for both nibbles', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $lcd = $board->lcd1602Parallel(7, 8, 9, 10, 11, 12);

    foreach ([7, 8, 9, 10, 11, 12] as $pin) {
        $board->pin($pin)->writes = [];
    }

    $lcd->clear(); // command 0x01: high nibble 0x0, low nibble 0x1

    // RS must be false for both nibbles (command mode)
    expect($board->pin(7)->writes)->toBe([false, false]);

    // d4: high nibble 0x0 → bit0 clear; low nibble 0x1 → bit0 set
    expect($board->pin(9)->writes)->toBe([false, true]);
});

it('setCursor(0, 1) sends command 0xC0 setting d7 and d6 high in first nibble', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $lcd = $board->lcd1602Parallel(7, 8, 9, 10, 11, 12);

    foreach ([7, 8, 9, 10, 11, 12] as $pin) {
        $board->pin($pin)->writes = [];
    }

    $lcd->setCursor(0, 1); // command: 0x80 | 0x40 = 0xC0; high nibble 0xC, low nibble 0x0

    // d7: high nibble 0xC → bit3 set (0xC & 0x8 = true); low nibble 0x0 → bit3 clear
    expect($board->pin(12)->writes)->toBe([true, false]);

    // d6: high nibble 0xC → bit2 set (0xC & 0x4 = true); low nibble 0x0 → bit2 clear
    expect($board->pin(11)->writes)->toBe([true, false]);
});
