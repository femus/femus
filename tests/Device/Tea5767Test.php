<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Device\Tea5767;
use Femus\Runtime\StreamSelectLoop;

it('tune writes the PLL word and the fixed setup bytes', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->tea5767()->tune(101.3);

    // 4 × (101.3 MHz + 225 kHz) / 32768 Hz = 12393 = 0x3069
    expect($board->fakeI2c()->writes)->toBe([[0x60, "\x30\x69\x10\x12\x40"]]);
});

it('tune sets the mute bit and 50 µs de-emphasis for Europe', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->tea5767(deEmphasis75us: false)->tune(101.3, mute: true);

    expect($board->fakeI2c()->writes[0][1])->toBe("\xB0\x69\x10\x12\x00");
});

it('tune refuses a frequency outside the band', function () {
    (new FakeBoard(new StreamSelectLoop()))->tea5767()->tune(120.0);
})->throws(InvalidArgumentException::class, 'outside the FM band');

it('status decodes frequency, level and stereo', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->fakeI2c()->queueRead(teaStatus(101.3, 10, stereo: true));

    expect($board->tea5767()->status())->toBe(['frequency' => 101.3, 'level' => 10, 'stereo' => true]);
});

it('scan tunes muted at every step and reads each point', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    foreach ([100.0, 100.0, 100.1, 100.2] as $mhz) {   // the first reading is thrown away
        $board->fakeI2c()->queueRead(teaStatus($mhz, 5));
    }

    $points = $board->tea5767()->scan(100.0, 100.2, settle: 0);

    expect(array_column($points, 'frequency'))->toBe([100.0, 100.1, 100.2])
        ->and($board->fakeI2c()->writes)->toHaveCount(4)
        ->and(ord($board->fakeI2c()->writes[0][1][0]) & 0x80)->toBe(0x80);
});

it('stations keeps one peak per hump and drops the noise floor', function () {
    $point = fn (float $mhz, int $level) => ['frequency' => $mhz, 'level' => $level, 'stereo' => false];
    $scan = [
        $point(88.0, 3), $point(88.1, 9), $point(88.2, 12), $point(88.3, 8),   // hump → 88.2
        $point(88.4, 4), $point(88.5, 10), $point(88.6, 10), $point(88.7, 5),  // flat top → 88.5 once
        $point(88.8, 6), $point(88.9, 5),                                      // below the threshold
    ];

    expect(array_column(Tea5767::stations($scan, 7), 'frequency'))->toBe([88.2, 88.5]);
});

it('by default stations stand 3 above the noise floor', function () {
    $point = fn (float $mhz, int $level) => ['frequency' => $mhz, 'level' => $level, 'stereo' => false];
    // a live Halifax scan: empty air sits at 7–9, the real stations at 10+
    $scan = [
        $point(90.3, 9), $point(90.5, 13), $point(90.7, 8), $point(90.9, 7), $point(91.1, 8),
        $point(91.3, 9), $point(91.5, 8), $point(101.3, 12), $point(101.5, 7),
    ];

    expect(Tea5767::noiseFloor($scan))->toBe(8)
        ->and(array_column(Tea5767::stations($scan), 'frequency'))->toBe([90.5, 101.3]);
});
