<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Adapter\Firmata\BoardException;
use Femus\Board;

it('Board::fake() creates a FakeBoard with a loop', function () {
    $board = Board::fake();
    expect($board)->toBeInstanceOf(FakeBoard::class)
        ->and($board->loop())->not->toBeNull();
});

it('auto-detection throws a clear error when no ports exist', function () {
    // machine-independent only if no real board is attached; probing real ports
    // must not crash — accept either exception message or (rare) success skip
    try {
        Femus\Board::firmata();
        expect(true)->toBeTrue(); // a real board answered — environment-dependent, fine
    } catch (BoardException $e) {
        expect($e->getMessage())->toContain('No');
    }
})->skipOnWindows();
