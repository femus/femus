<?php

declare(strict_types=1);

use Femus\Adapter\Firmata\BoardException;
use Femus\Adapter\Firmata\FirmataBoard;
use Femus\Contracts\PinMode;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\InMemoryTransport;

function readyBoard(InMemoryTransport $transport): FirmataBoard
{
    $board = new FirmataBoard($transport, new StreamSelectLoop());
    $transport->feed("\xF9\x02\x05"); // StandardFirmata sends its version on start
    $board->awaitReady();

    return $board;
}

it('awaitReady succeeds after receiving the firmware version', function () {
    $board = readyBoard(new InMemoryTransport());
    expect($board)->toBeInstanceOf(FirmataBoard::class);
});

it('awaitReady throws an exception on timeout', function () {
    $board = new FirmataBoard(new InMemoryTransport(), new StreamSelectLoop(), handshakeTimeout: 0.05);
    $board->awaitReady();
})->throws(BoardException::class);

it('LED on pin 13: setPinMode + digitalWrite for port 1', function () {
    $transport = new InMemoryTransport();
    $board = readyBoard($transport);
    $transport->written = '';

    $board->led(13)->on();

    expect($transport->written)->toBe(
        "\xF4\x0D\x01"      // SET_PIN_MODE pin 13 OUTPUT
        . "\x91\x20\x00",   // DIGITAL_MESSAGE port 1, bit 5
    );
});

it('requesting an input pin enables reporting for its port', function () {
    $transport = new InMemoryTransport();
    $board = readyBoard($transport);
    $transport->written = '';

    $board->digitalPin(2, PinMode::InputPullUp);

    expect($transport->written)->toBe(
        "\xF4\x02\x0B"   // SET_PIN_MODE pin 2 PULLUP
        . "\xD0\x01",    // REPORT_DIGITAL port 0 on
    );
});

it('an incoming digital message updates the pin and calls onChange', function () {
    $transport = new InMemoryTransport();
    $board = readyBoard($transport);
    $pin = $board->digitalPin(2, PinMode::Input);

    $seen = null;
    $pin->onChange(function (bool $high) use (&$seen, $board) {
        $seen = $high;
        $board->stop();
    });

    $transport->feed("\x90\x04\x00"); // port 0: pin 2 HIGH
    $board->loop()->addTimer(1.0, fn () => $board->stop()); // safety guard against hanging
    $board->run();

    expect($seen)->toBeTrue()->and($pin->read())->toBeTrue();
});

it('an incoming LOW resets the pin and calls onChange with false', function () {
    $transport = new InMemoryTransport();
    $board = readyBoard($transport);
    $pin = $board->digitalPin(2, PinMode::Input);
    $transport->feed("\x90\x04\x00"); // pin 2 HIGH

    $seen = null;
    $pin->onChange(function (bool $high) use (&$seen, $board) {
        $seen = $high;
        $board->stop();
    });

    $transport->feed("\x90\x00\x00"); // pin 2 LOW
    $board->loop()->addTimer(1.0, fn () => $board->stop()); // safety guard against hanging
    $board->run();

    expect($seen)->toBeFalse()->and($pin->read())->toBeFalse();
});

it('two output pins on the same port do not overwrite each other', function () {
    $transport = new InMemoryTransport();
    $board = readyBoard($transport);
    $led2 = $board->led(2); // port 0, bit 2
    $led3 = $board->led(3); // port 0, bit 3
    $led2->on();
    $transport->written = '';

    $led3->on(); // bitmask must preserve pin 2 bit

    expect($transport->written)->toBe("\x90\x0C\x00"); // 0b1100
});

it('awaitReady actively queries the protocol version', function () {
    $transport = new InMemoryTransport();
    $board = new FirmataBoard($transport, new StreamSelectLoop(), handshakeTimeout: 0.05);
    try {
        $board->awaitReady();
    } catch (BoardException) {
    }
    // boards on Bluetooth links do not reset on connect — we must ask (0xF9)
    expect($transport->written)->toContain("\xF9");
});
