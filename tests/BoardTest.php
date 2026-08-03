<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Board;

it('Board::fake() creates a FakeBoard with a loop', function () {
    $board = Board::fake();
    expect($board)->toBeInstanceOf(FakeBoard::class)
        ->and($board->loop())->not->toBeNull();
});
