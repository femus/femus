<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// GY-521: VCC -> 5V, GND -> GND, SDA -> A4, SCL -> A5
$board = Board::firmata($argv[1] ?? '/dev/ttyUSB0');

$mpu = $board->mpu6050();

$board->loop()->addPeriodicTimer(0.5, function () use ($mpu) {
    $a = $mpu->readAccel();
    printf("accel: x=%+.2fg y=%+.2fg z=%+.2fg  t=%.1f°C\n", $a['x'], $a['y'], $a['z'], $mpu->readTemperature());
});

echo "Tilt the board. Ctrl+C to exit.\n";
$board->run();
