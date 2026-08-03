<?php

declare(strict_types=1);

use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\InMemoryTransport;

it('accumulates outgoing bytes', function () {
    $transport = new InMemoryTransport();
    $transport->write("\x01");
    $transport->write("\x02");
    expect($transport->written)->toBe("\x01\x02");
});

it('feed delivers bytes via the event loop', function () {
    $transport = new InMemoryTransport();
    $loop = new StreamSelectLoop();
    $received = '';
    $loop->addReadStream($transport->stream(), function () use ($transport, &$received, $loop) {
        $received .= $transport->readAvailable();
        $loop->stop();
    });
    $transport->feed("\xF9\x02\x05");
    $loop->run();
    expect($received)->toBe("\xF9\x02\x05");
});
