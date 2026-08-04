<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

it('delivers every raw reading to listeners', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $input = $board->scaleInput(3, 2);
    $seen = [];
    $input->onRawReading(function (int $raw) use (&$seen) { $seen[] = $raw; });
    $board->simulateScaleReading(3, 2, 8000);
    $board->simulateScaleReading(3, 2, 8000); // repeated value still delivered
    expect($seen)->toBe([8000, 8000])
        ->and($input->lastRaw())->toBe(8000);
});

it('caches the input per pin pair', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    expect($board->scaleInput(3, 2))->toBe($board->scaleInput(3, 2))
        ->and($board->scaleInput(3, 2))->not->toBe($board->scaleInput(5, 6));
});

it('schedules readings through the event loop', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $input = $board->scaleInput(3, 2);
    $board->scheduleScaleReading(0.01, 3, 2, 12345);
    $board->loop()->addTimer(0.05, fn () => $board->stop());
    $board->run();
    expect($input->lastRaw())->toBe(12345);
});
