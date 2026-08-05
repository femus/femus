<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;
use Femus\Contracts\RadioLink;
use Femus\Contracts\RadioMessage;

// Usage: php examples/radio-chat.php [port] [my-address] [peer-address]
$board = Board::firmata($argv[1] ?? null);
$address = (int) ($argv[2] ?? 1);
$peer = (int) ($argv[3] ?? RadioLink::BROADCAST);

$radio = $board->radioLink($address);

$radio->onMessage(function (RadioMessage $m) {
    printf("\r[node %d] %s\n> ", $m->from, $m->message);
});

stream_set_blocking(STDIN, false);
$board->loop()->addReadStream(STDIN, function () use ($radio, $peer) {
    $line = trim((string) fgets(STDIN));
    if ($line !== '') {
        $radio->send($peer, $line);
        echo "(sent)\n> ";
    }
});

printf("Radio chat — node %d, sending to %s. Type a message and press Enter.\n> ",
    $address, $peer === RadioLink::BROADCAST ? 'broadcast' : "node {$peer}");
$board->run();
