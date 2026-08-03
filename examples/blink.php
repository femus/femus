<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// Порт: macOS — /dev/cu.usbserial-XXXX или /dev/cu.usbmodemXXXX (ls /dev/cu.*),
// Linux — /dev/ttyUSB0 или /dev/ttyACM0.
$board = Board::firmata($argv[1] ?? '/dev/ttyUSB0');

$led = $board->led(13); // встроенный светодиод Arduino
$led->blink(0.5);

echo "Мигаем светодиодом на пине 13. Ctrl+C для выхода.\n";
$board->run();
