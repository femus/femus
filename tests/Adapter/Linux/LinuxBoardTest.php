<?php

declare(strict_types=1);

use Femus\Adapter\Linux\LinuxBoard;
use Femus\Runtime\StreamSelectLoop;
use Femus\Tests\Cli\FakeCommandRunner;

it('drives an LED through pinctrl', function () {
    $runner = new FakeCommandRunner();
    $board = new LinuxBoard($runner, new StreamSelectLoop());

    $led = $board->led(17);
    $led->on();
    expect(end($runner->calls))->toBe(['pinctrl', 'set', '17', 'dh']);

    $led->off();
    expect(end($runner->calls))->toBe(['pinctrl', 'set', '17', 'dl']);
});

it('reuses one pin object per number', function () {
    $board = new LinuxBoard(new FakeCommandRunner(), new StreamSelectLoop());
    expect($board->digitalPin(17, Femus\Contracts\PinMode::Output))
        ->toBe($board->digitalPin(17, Femus\Contracts\PinMode::Output));
});

it('rejects analog reads with a helpful message', function () {
    $board = new LinuxBoard(new FakeCommandRunner(), new StreamSelectLoop());
    expect(fn () => $board->analogPin(0))->toThrow(LogicException::class, 'no ADC');
});

it('points radio and scale users to the Firmata board', function () {
    $board = new LinuxBoard(new FakeCommandRunner(), new StreamSelectLoop());
    expect(fn () => $board->radioLink(1))->toThrow(LogicException::class, 'Board::firmata()')
        ->and(fn () => $board->scaleInput(3, 2))->toThrow(LogicException::class, 'Board::firmata()');
});
