<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

$board = Board::firmata($argv[1] ?? '/dev/ttyUSB0');

$button = $board->button(2);   // кнопка: пин 2 — GND (внутренняя подтяжка)
$led = $board->led(13);

$button->onPress(fn () => $led->on());
$button->onRelease(fn () => $led->off());

echo "Держи кнопку — горит светодиод. Ctrl+C для выхода.\n";
$board->run();
