<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// Fade an RGB LED through the color wheel with PWM.
// Wire R/G/B (via resistors) to PWM pins 9, 10, 11; common cathode to GND.

$board = Board::firmata($argv[1] ?? null);
$rgb = $board->rgbLed(redPin: 9, greenPin: 10, bluePin: 11);

$hue = 0;
$board->loop()->addPeriodicTimer(0.05, function () use ($rgb, &$hue): void {
    // simple hue → RGB sweep
    $h = ($hue % 360) / 60;
    $x = (int) (255 * (1 - abs(fmod($h, 2) - 1)));
    [$r, $g, $b] = match ((int) $h) {
        0 => [255, $x, 0], 1 => [$x, 255, 0], 2 => [0, 255, $x],
        3 => [0, $x, 255], 4 => [$x, 0, 255], default => [255, 0, $x],
    };
    $rgb->color($r, $g, $b);
    $hue += 3;
});

fwrite(STDOUT, "Fading RGB LED on pins 9/10/11. Ctrl+C to quit.\n");
$board->run();
