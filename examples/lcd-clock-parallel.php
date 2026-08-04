<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// Wiring (LCD pin → Arduino Nano):
//   VSS  → GND
//   VDD  → 5V
//   VO   → contrast: pot middle pin; or 1–2 kΩ resistor to GND; or GND directly for max contrast
//   RS   → D7
//   RW   → GND  (tied permanently; write-only mode)
//   E    → D8
//   D4   → D9
//   D5   → D10
//   D6   → D11
//   D7   → D12
//   A    → 5V   (backlight anode)
//   K    → GND  (backlight cathode)

$board = Board::firmata($argv[1] ?? null);

$lcd = $board->lcd1602Parallel(7, 8, 9, 10, 11, 12);
$lcd->write('femus');

$board->loop()->addPeriodicTimer(1.0, function () use ($lcd) {
    $lcd->setCursor(0, 1);
    $lcd->write(date('H:i:s'));
});

echo "Clock on LCD (parallel). Ctrl+C to exit.\n";
$board->run();
