<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// HX711: VCC -> 5V, GND -> GND, DT -> D3, SCK -> D2 (FemusFirmata required)
$board = Board::firmata($argv[1] ?? null);

$cell = $board->loadCell(3, 2, thresholdGrams: 0.5);

$cell->onChange(function (float $grams) {
    printf("Weight: %+.1f (raw units until calibrated)\n", $grams);
});

echo "Waiting for the first reading, then taring...\n";

// Wait for the first reading with a 5 second guard
$start = time();
while ($cell->raw() === null) {
    if (time() - $start > 5) {
        echo "Warning: No reading after 5 seconds. Check wiring.\n";
        exit(1);
    }
    $board->loop()->tick(0.1);
}

$cell->tare();
echo "Tared. Put something on the scale. Ctrl+C to exit.\n";

$board->run();
