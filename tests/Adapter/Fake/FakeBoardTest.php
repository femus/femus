<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Contracts\PinMode;
use Femus\Runtime\StreamSelectLoop;

it('выдаёт один и тот же пин по номеру', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->digitalPin(4, PinMode::Input);
    expect($board->digitalPin(4, PinMode::Input))->toBe($pin)
        ->and($pin->number())->toBe(4);
});

it('write/read работают', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->digitalPin(7, PinMode::Output);
    $pin->write(true);
    expect($pin->read())->toBeTrue();
});

it('simulateInput зовёт onChange-листенеры', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->digitalPin(2, PinMode::Input);
    $seen = [];
    $pin->onChange(function (bool $high) use (&$seen) { $seen[] = $high; });
    $board->simulateInput(2, true);
    $board->simulateInput(2, true);  // без изменения — не событие
    $board->simulateInput(2, false);
    expect($seen)->toBe([true, false]);
});

it('scheduleInput инжектит событие через event loop', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->digitalPin(2, PinMode::Input);
    $seen = false;
    $pin->onChange(function (bool $high) use (&$seen) { $seen = $high; });
    $board->scheduleInput(0.01, 2, true);
    $board->run();
    expect($seen)->toBeTrue();
});
