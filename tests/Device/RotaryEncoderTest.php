<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

/** One detent = DT set to the desired level, then a CLK high→low pulse. */
function detent(FakeBoard $board, int $clk, int $dt, bool $clockwise): void
{
    $board->simulateInput($dt, $clockwise);
    $board->simulateInput($clk, true);   // rising edge — ignored
    $board->simulateInput($clk, false);  // falling edge — decoded
}

it('counts up when turned clockwise', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $enc = $board->rotaryEncoder(clkPin: 4, dtPin: 5);

    detent($board, 4, 5, clockwise: true);
    detent($board, 4, 5, clockwise: true);

    expect($enc->position())->toBe(2);
});

it('counts down when turned counter-clockwise', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $enc = $board->rotaryEncoder(clkPin: 4, dtPin: 5);

    detent($board, 4, 5, clockwise: false);

    expect($enc->position())->toBe(-1);
});

it('reports each step with a direction', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $enc = $board->rotaryEncoder(clkPin: 4, dtPin: 5);
    $steps = [];
    $enc->onTurn(function (int $dir, int $pos) use (&$steps) { $steps[] = [$dir, $pos]; });

    detent($board, 4, 5, clockwise: true);
    detent($board, 4, 5, clockwise: false);

    expect($steps)->toBe([[1, 1], [-1, 0]]);
});

it('ignores the rising edge of CLK', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $enc = $board->rotaryEncoder(clkPin: 4, dtPin: 5);

    $board->simulateInput(5, true);
    $board->simulateInput(4, true);   // only a rising edge — no step yet

    expect($enc->position())->toBe(0);
});

it('can reset its position', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $enc = $board->rotaryEncoder(clkPin: 4, dtPin: 5);

    detent($board, 4, 5, clockwise: true);
    $enc->resetPosition();

    expect($enc->position())->toBe(0);
});

it('exposes a push switch when wired', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $enc = $board->rotaryEncoder(clkPin: 4, dtPin: 5, swPin: 6);

    expect($enc->button())->toBeInstanceOf(\Femus\Device\Button::class);
});

it('has no button when SW is not wired', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $enc = $board->rotaryEncoder(clkPin: 4, dtPin: 5);

    expect($enc->button())->toBeNull();
});
