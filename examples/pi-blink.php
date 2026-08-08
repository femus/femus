<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// Blink an LED on the Raspberry Pi's own GPIO — no Arduino involved.
// Wire an LED (+ resistor) from GPIO17 to GND. Usage: php examples/pi-blink.php [gpio]

$gpio = (int) ($argv[1] ?? 17);

$board = Board::linux();
$board->led($gpio)->blink(0.5);

fwrite(STDOUT, "Blinking GPIO{$gpio} on the Pi itself. Ctrl+C to quit.\n");
$board->run();
