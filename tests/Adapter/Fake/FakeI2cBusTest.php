<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Contracts\I2cException;
use Femus\Runtime\StreamSelectLoop;

it('records written bytes', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $bus = $board->i2c();
    $bus->write(0x27, "\x0C");
    expect($board->fakeI2c()->writes)->toBe([[0x27, "\x0C"]]);
});

it('readRegister returns from queue', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->fakeI2c()->queueRead("\x40\x00");
    expect($board->i2c()->readRegister(0x68, 0x3B, 2))->toBe("\x40\x00");
});

it('readRegister with empty queue throws exception', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->i2c()->readRegister(0x68, 0x3B, 2);
})->throws(I2cException::class);
