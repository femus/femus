<?php

declare(strict_types=1);

use Femus\Adapter\Firmata\FirmataBoard;
use Femus\Contracts\I2cException;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\InMemoryTransport;

function readyI2cBoard(InMemoryTransport $transport): FirmataBoard
{
    $board = new FirmataBoard($transport, new StreamSelectLoop());
    $transport->feed("\xF9\x02\x05");
    $board->awaitReady();

    return $board;
}

it('first call to i2c() sends I2C_CONFIG', function () {
    $transport = new InMemoryTransport();
    $board = readyI2cBoard($transport);
    $transport->written = '';

    $board->i2c();

    expect($transport->written)->toBe("\xF0\x78\x00\x00\xF7");
});

it('write encodes the request', function () {
    $transport = new InMemoryTransport();
    $board = readyI2cBoard($transport);
    $bus = $board->i2c();
    $transport->written = '';

    $bus->write(0x27, "\x0C");

    expect($transport->written)->toBe("\xF0\x76\x27\x00\x0C\x00\xF7");
});

it('readRegister blocks until reply and decodes data', function () {
    $transport = new InMemoryTransport();
    $board = readyI2cBoard($transport);
    $bus = $board->i2c();

    // reply arrives via event loop: schedule feed after read starts
    $board->loop()->addTimer(0.01, function () use ($transport) {
        $transport->feed("\xF0\x77\x68\x00\x3B\x00\x40\x00\x00\x00\xF7");
    });

    $data = $bus->readRegister(0x68, 0x3B, 2);

    expect($data)->toBe("\x40\x00");
});

it('readRegister throws on timeout', function () {
    $transport = new InMemoryTransport();
    $board = readyI2cBoard($transport);
    $board->i2c()->readRegister(0x68, 0x3B, 2);
})->throws(I2cException::class);
