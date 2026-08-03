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
    $transport->feed("\xF9\x02\x05"); // StandardFirmata шлёт версию при старте
    $board->awaitReady();

    return $board;
}

it('awaitReady проходит после версии от прошивки', function () {
    $board = readyBoard(new InMemoryTransport());
    expect($board)->toBeInstanceOf(FirmataBoard::class);
});

it('awaitReady кидает исключение по таймауту', function () {
    $board = new FirmataBoard(new InMemoryTransport(), new StreamSelectLoop(), handshakeTimeout: 0.05);
    $board->awaitReady();
})->throws(BoardException::class);

it('led на пине 13: setPinMode + digitalWrite порта 1', function () {
    $transport = new InMemoryTransport();
    $board = readyBoard($transport);
    $transport->written = '';

    $board->led(13)->on();

    expect($transport->written)->toBe(
        "\xF4\x0D\x01"      // SET_PIN_MODE pin 13 OUTPUT
        . "\x91\x20\x00",   // DIGITAL_MESSAGE порт 1, бит 5
    );
});

it('запрос входного пина включает репортинг его порта', function () {
    $transport = new InMemoryTransport();
    $board = readyBoard($transport);
    $transport->written = '';

    $board->digitalPin(2, PinMode::InputPullUp);

    expect($transport->written)->toBe(
        "\xF4\x02\x0B"   // SET_PIN_MODE pin 2 PULLUP
        . "\xD0\x01",    // REPORT_DIGITAL порт 0 on
    );
});

it('входящее digital message обновляет пин и зовёт onChange', function () {
    $transport = new InMemoryTransport();
    $board = readyBoard($transport);
    $pin = $board->digitalPin(2, PinMode::Input);

    $seen = null;
    $pin->onChange(function (bool $high) use (&$seen, $board) {
        $seen = $high;
        $board->stop();
    });

    $transport->feed("\x90\x04\x00"); // порт 0: пин 2 HIGH
    $board->loop()->addTimer(1.0, fn () => $board->stop()); // страховка от зависания
    $board->run();

    expect($seen)->toBeTrue()->and($pin->read())->toBeTrue();
});

it('два выходных пина одного порта не затирают друг друга', function () {
    $transport = new InMemoryTransport();
    $board = readyBoard($transport);
    $led2 = $board->led(2); // порт 0, бит 2
    $led3 = $board->led(3); // порт 0, бит 3
    $led2->on();
    $transport->written = '';

    $led3->on(); // битмаска обязана сохранить бит пина 2

    expect($transport->written)->toBe("\x90\x0C\x00"); // 0b1100
});
