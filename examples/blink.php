<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// Port: macOS — /dev/cu.usbserial-XXXX or /dev/cu.usbmodemXXXX (ls /dev/cu.*),
// Linux — /dev/ttyUSB0 or /dev/ttyACM0.
$board = Board::firmata($argv[1] ?? '/dev/ttyUSB0');

$led = $board->led(13); // built-in Arduino LED
$led->blink(0.5);

echo "Blinking LED on pin 13. Press Ctrl+C to quit.\n";
$board->run();
