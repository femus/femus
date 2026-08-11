<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// KY-040 rotary encoder: CLK -> D4, DT -> D5, SW -> D6 (push knob), + -> 5V, GND -> GND.
// Turn the knob to change a value; press it to confirm.
// Usage: php examples/rotary-encoder.php [port]
$board = Board::firmata($argv[1] ?? null);

$encoder = $board->rotaryEncoder(clkPin: 4, dtPin: 5, swPin: 6);

$volume = 50;

$encoder->onTurn(function (int $direction) use (&$volume) {
    $volume = max(0, min(100, $volume + $direction));
    printf("Volume: %d%%\n", $volume);
});

$encoder->button()?->onPress(function () use (&$volume) {
    printf("Confirmed at %d%%\n", $volume);
});

echo "Turn the knob (press to confirm). Ctrl+C to exit.\n";
$board->run();
