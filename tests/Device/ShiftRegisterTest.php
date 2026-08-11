<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

/** Forget the writes made by the constructor's initial all-off flush. */
function forgetInitialFlush(FakeBoard $board, int ...$pins): void
{
    foreach ($pins as $pin) {
        $board->pin($pin)->writes = [];
    }
}

it('flushes zeros on construction so power-up garbage never shows', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->shiftRegister(dataPin: 2, clockPin: 3, latchPin: 4);

    expect($board->pin(2)->writes)->toBe(array_fill(0, 8, false))
        ->and($board->pin(4)->writes)->toBe([false, true]);
});

it('shifts the byte out MSB-first so bit 0 lands on Q0', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sr = $board->shiftRegister(dataPin: 2, clockPin: 3, latchPin: 4);
    forgetInitialFlush($board, 2);

    $sr->write(0b10000001);

    // The first bit shifted in ends up on Q7 after 8 clocks, so Q7 goes first.
    expect($board->pin(2)->writes)->toBe([true, false, false, false, false, false, false, true]);
});

it('pulses the clock once per bit', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sr = $board->shiftRegister(dataPin: 2, clockPin: 3, latchPin: 4);
    forgetInitialFlush($board, 3);

    $sr->write(0b10000001);

    $risingEdges = count(array_filter($board->pin(3)->writes));
    expect($risingEdges)->toBe(8);
});

it('latches low before shifting and high after', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sr = $board->shiftRegister(dataPin: 2, clockPin: 3, latchPin: 4);
    forgetInitialFlush($board, 4);

    $sr->write(0b1);

    expect($board->pin(4)->writes)->toBe([false, true]);
});

it('sets a single output high and remembers it', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sr = $board->shiftRegister(dataPin: 2, clockPin: 3, latchPin: 4);

    $sr->set(0, true);

    expect($sr->get(0))->toBeTrue()
        ->and($sr->get(1))->toBeFalse()
        ->and($sr->state())->toBe(0b1);
});

it('drives 16 outputs with two chained chips, last chip byte first', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sr = $board->shiftRegister(dataPin: 2, clockPin: 3, latchPin: 4, chips: 2);
    forgetInitialFlush($board, 2);

    $sr->write(1 << 8); // Q0 of the second chip in the chain

    expect($sr->outputs())->toBe(16)
        ->and($board->pin(2)->writes)->toBe([
            false, false, false, false, false, false, false, true, // second chip, Q7..Q0
            false, false, false, false, false, false, false, false, // first chip, Q7..Q0
        ]);
});

it('clears all outputs at once', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sr = $board->shiftRegister(dataPin: 2, clockPin: 3, latchPin: 4);
    $sr->write(0xFF);

    $sr->clear();

    expect($sr->state())->toBe(0)
        ->and(end($board->pin(2)->writes))->toBeFalse();
});

it('rejects an output number beyond the chain', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sr = $board->shiftRegister(dataPin: 2, clockPin: 3, latchPin: 4);

    $sr->set(8, true);
})->throws(InvalidArgumentException::class);
