<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// Usage: php examples/button-led.php [port] [button-pin]
$board = Board::firmata($argv[1] ?? null);

$buttonPin = (int) ($argv[2] ?? 2); // button: pin — GND (internal pull-up)
$button = $board->button($buttonPin);
$led = $board->led(13);

printf("Button on D%d.\n", $buttonPin);

$button->onPress(fn () => $led->on());
$button->onRelease(fn () => $led->off());

echo "Hold the button — LED lights up. Press Ctrl+C to quit.\n";
$board->run();
