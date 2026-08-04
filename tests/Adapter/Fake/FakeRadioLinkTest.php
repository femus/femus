<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Contracts\RadioLink;
use Femus\Contracts\RadioMessage;
use Femus\Runtime\StreamSelectLoop;

it('records sent messages', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $radio = $board->radioLink(1);
    $radio->send(RadioLink::BROADCAST, 'ping');
    expect($board->fakeRadio(1)->sent)->toBe([[127, 'ping']])
        ->and($radio->address())->toBe(1);
});

it('delivers simulated incoming messages', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $radio = $board->radioLink(1);
    $seen = null;
    $radio->onMessage(function (RadioMessage $m) use (&$seen) { $seen = $m; });
    $board->simulateRadioMessage(1, from: 2, to: 1, message: 'Hi');
    expect($seen->from)->toBe(2)->and($seen->message)->toBe('Hi');
});

it('rejects out-of-range address and oversized message', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $radio = $board->radioLink(1);
    expect(fn () => $radio->send(200, 'x'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $radio->send(2, str_repeat('a', 51)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $radio->send(2, ''))->toThrow(InvalidArgumentException::class);
});
