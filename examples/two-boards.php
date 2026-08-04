<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;
use Femus\Runtime\StreamSelectLoop;

// Two boards, one PHP process: a button on board A drives the LED on board B.
// Usage: php examples/two-boards.php /dev/cu.usbserial-A /dev/cu.usbserial-B
$loop = new StreamSelectLoop();
$boardA = Board::firmata($argv[1] ?? null, loop: $loop);
$boardB = Board::firmata($argv[2] ?? throw new InvalidArgumentException('Pass two ports'), loop: $loop);

$button = $boardA->button(2);
$led = $boardB->led(13);

$button->onPress(fn () => $led->on());
$button->onRelease(fn () => $led->off());

echo "Hold the button on board A — the LED on board B lights up. Ctrl+C to exit.\n";
$loop->run();
