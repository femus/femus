<?php

declare(strict_types=1);

use Femus\Adapter\Firmata\FirmataBoard;
use Femus\Contracts\RadioMessage;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\InMemoryTransport;

function readyRadioBoard(InMemoryTransport $transport): FirmataBoard
{
    $board = new FirmataBoard($transport, new StreamSelectLoop());
    $transport->feed("\xF9\x02\x05");
    $board->awaitReady();

    return $board;
}

it('sends the attach frame on first request', function () {
    $transport = new InMemoryTransport();
    $board = readyRadioBoard($transport);
    $transport->written = '';
    $board->radioLink(1);
    expect($transport->written)->toBe("\xF0\x0D\x00\x01\x0B\x0C\xF7");
});

it('send writes the encoded frame', function () {
    $transport = new InMemoryTransport();
    $board = readyRadioBoard($transport);
    $radio = $board->radioLink(1);
    $transport->written = '';
    $radio->send(3, 'Hi');
    expect($transport->written)->toBe("\xF0\x0D\x01\x03\x48\x00\x69\x00\xF7");
});

it('delivers incoming frames and ignores foreign sysex', function () {
    $transport = new InMemoryTransport();
    $board = readyRadioBoard($transport);
    $radio = $board->radioLink(1);
    $seen = null;
    $radio->onMessage(function (RadioMessage $m) use (&$seen, $board) {
        $seen = $m;
        $board->stop();
    });
    $transport->feed("\xF0\x79\x02\x05\xF7");
    $transport->feed("\xF0\x0D\x02\x02\x01\x48\x00\x69\x00\xF7");
    $board->loop()->addTimer(1.0, fn () => $board->stop());
    $board->run();
    expect($seen->from)->toBe(2)->and($seen->to)->toBe(1)->and($seen->message)->toBe('Hi');
});
