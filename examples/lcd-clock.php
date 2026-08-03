<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// LCD1602 with I2C backpack: VCC -> 5V, GND -> GND, SDA -> A4, SCL -> A5
$board = Board::firmata($argv[1] ?? '/dev/ttyUSB0');

$lcd = $board->lcd1602();
$lcd->write('femus');

$board->loop()->addPeriodicTimer(1.0, function () use ($lcd) {
    $lcd->setCursor(0, 1);
    $lcd->write(date('H:i:s'));
});

echo "Clock on LCD. Ctrl+C to exit.\n";
$board->run();
