<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

$board = Board::firmata($argv[1] ?? '/dev/ttyUSB0');

$button = $board->button(2);   // button: pin 2 — GND (internal pull-up)
$led = $board->led(13);

$button->onPress(fn () => $led->on());
$button->onRelease(fn () => $led->off());

echo "Hold the button — LED lights up. Press Ctrl+C to quit.\n";
$board->run();
