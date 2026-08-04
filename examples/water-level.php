<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// Water level sensor: S -> A0, + -> 5V, - -> GND
$board = Board::firmata($argv[1] ?? null);

$sensor = $board->analogSensor(0, threshold: 0.02);

$sensor->onChange(function (float $level) {
    printf("Level: %.1f%%\n", $level * 100);
});

echo "Dip the sensor in water. Ctrl+C to exit.\n";
$board->run();
