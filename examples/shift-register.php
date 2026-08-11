<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// 74HC595 shift register: 8 LEDs from 3 pins. Wiring (chip notch up):
//   DS/SER (14) -> D2, SHCP/SRCLK (11) -> D3, STCP/RCLK (12) -> D4,
//   VCC (16) + MR (10) -> 5V, GND (8) + OE (13) -> GND.
// LEDs from Q0..Q7 (15, 1-7) through 220 Ohm resistors to GND.
//
// Usage: php examples/shift-register.php [port]
$board = Board::firmata($argv[1] ?? null);

$sr = $board->shiftRegister(dataPin: 2, clockPin: 3, latchPin: 4);

// Knight Rider: one lit LED sweeping back and forth.
$position = 0;
$direction = 1;
$board->loop()->addPeriodicTimer(0.1, function () use ($sr, &$position, &$direction): void {
    $sr->write(1 << $position);
    if ($position === $sr->outputs() - 1 || ($position === 0 && $direction === -1)) {
        $direction = -$direction;
    }
    $position += $direction;
});

echo "Sweeping 8 LEDs through a 74HC595. Ctrl+C to exit.\n";
$board->run();
