<?php

declare(strict_types=1);

use Femus\Adapter\Firmata\FirmataBoard;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\InMemoryTransport;

function readyScaleBoard(InMemoryTransport $transport): FirmataBoard
{
    $board = new FirmataBoard($transport, new StreamSelectLoop());
    $transport->feed("\xF9\x02\x05");
    $board->awaitReady();

    return $board;
}

it('sends the attach frame on first request', function () {
    $transport = new InMemoryTransport();
    $board = readyScaleBoard($transport);
    $transport->written = '';

    $board->scaleInput(3, 2);

    expect($transport->written)->toBe("\xF0\x0E\x00\x03\x02\xF7");
});

it('delivers incoming readings and ignores foreign sysex', function () {
    $transport = new InMemoryTransport();
    $board = readyScaleBoard($transport);
    $input = $board->scaleInput(3, 2);

    $seen = null;
    $input->onRawReading(function (int $raw) use (&$seen, $board) {
        $seen = $raw;
        $board->stop();
    });

    $transport->feed("\xF0\x79\x02\x05\xF7");                      // foreign sysex
    $transport->feed("\xF0\x0E\x01\x64\x00\x00\x00\x00\xF7");      // reading 100
    $board->loop()->addTimer(1.0, fn () => $board->stop());
    $board->run();

    expect($seen)->toBe(100)->and($input->lastRaw())->toBe(100);
});
