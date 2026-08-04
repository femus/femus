<?php

declare(strict_types=1);

namespace Tests\Gsm;

use Femus\Gsm\AtChannel;
use Femus\Gsm\AtException;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\InMemoryTransport;

function atChannel(float $timeout = 1.0): array
{
    $transport = new InMemoryTransport();
    $loop = new StreamSelectLoop();

    return [new AtChannel($transport, $loop, $timeout), $transport, $loop];
}

it('sends a command and collects the response until OK', function () {
    [$channel, $transport, $loop] = atChannel();
    $loop->addTimer(0.01, fn () => $transport->feed("AT+CSQ\r\n+CSQ: 21,0\r\n\r\nOK\r\n"));
    $response = $channel->send('AT+CSQ');
    expect($transport->written)->toBe("AT+CSQ\r")
        ->and($response->ok)->toBeTrue()
        ->and($response->lines)->toBe(['+CSQ: 21,0'])   // echo and empty lines stripped
        ->and($response->firstLine())->toBe('+CSQ: 21,0');
});

it('marks ERROR responses as not ok', function () {
    [$channel, $transport, $loop] = atChannel();
    $loop->addTimer(0.01, fn () => $transport->feed("\r\n+CME ERROR: 10\r\n"));
    $response = $channel->send('AT+CPIN?');
    expect($response->ok)->toBeFalse()
        ->and($response->lines)->toBe(['+CME ERROR: 10']);
});

it('survives responses split across chunks', function () {
    [$channel, $transport, $loop] = atChannel();
    $loop->addTimer(0.01, fn () => $transport->feed("+CSQ: 2"));
    $loop->addTimer(0.02, fn () => $transport->feed("1,0\r\nOK\r\n"));
    $response = $channel->send('AT+CSQ');
    expect($response->lines)->toBe(['+CSQ: 21,0'])->and($response->ok)->toBeTrue();
});

it('throws on timeout', function () {
    [$channel] = atChannel(timeout: 0.05);
    $channel->send('AT');
})->throws(AtException::class);

it('delivers unsolicited lines outside a command', function () {
    [$channel, $transport, $loop] = atChannel();
    $seen = [];
    $channel->onUnsolicited(function (string $line) use (&$seen, $loop) {
        $seen[] = $line;
        $loop->stop();
    });
    $transport->feed("\r\n+CMTI: \"SM\",3\r\n");
    $loop->addTimer(1.0, fn () => $loop->stop());
    $loop->run();
    expect($seen)->toBe(['+CMTI: "SM",3']);
});

it('queues unsolicited lines during a command and delivers them after', function () {
    [$channel, $transport, $loop] = atChannel();
    $order = [];
    $channel->onUnsolicited(function (string $line) use (&$order, $channel) {
        $order[] = 'unsolicited:' . $line . ':busy=' . ($channel->isBusy() ? '1' : '0');
    });
    $loop->addTimer(0.01, fn () => $transport->feed("+CMTI: \"SM\",7\r\nOK\r\n"));
    $response = $channel->send('AT');
    $order[] = 'response:' . ($response->ok ? 'ok' : 'error');
    // unsolicited delivered after command completed (busy=0), before send() returned
    expect($order)->toBe(['unsolicited:+CMTI: "SM",7:busy=0', 'response:ok']);
});

it('delivers queued unsolicited lines even when the command times out', function () {
    [$channel, $transport, $loop] = atChannel(timeout: 0.05);
    $seen = [];
    $channel->onUnsolicited(function (string $line) use (&$seen) { $seen[] = $line; });
    $loop->addTimer(0.01, fn () => $transport->feed("+CMTI: \"SM\",5\r\n")); // no OK ever
    try {
        $channel->send('AT');
    } catch (AtException) {
    }
    expect($seen)->toBe(['+CMTI: "SM",5']);
});

it('sends a payload after the prompt', function () {
    [$channel, $transport, $loop] = atChannel();
    $loop->addTimer(0.01, fn () => $transport->feed("\r\n> "));
    $loop->addTimer(0.03, fn () => $transport->feed("\r\n+CMGS: 4\r\n\r\nOK\r\n"));
    $response = $channel->sendExpectingPrompt('AT+CMGS="+79161234567"', 'Hello');
    expect($transport->written)->toBe("AT+CMGS=\"+79161234567\"\rHello\x1A")
        ->and($response->ok)->toBeTrue()
        ->and($response->lines)->toBe(['+CMGS: 4']);
});

it('throws when the prompt never arrives', function () {
    [$channel] = atChannel(timeout: 0.05);
    $channel->sendExpectingPrompt('AT+CMGS="+79161234567"', 'Hello');
})->throws(AtException::class);

it('delivers queued unsolicited lines when the sms prompt times out', function () {
    [$channel, $transport, $loop] = atChannel(timeout: 0.05);
    $seen = [];
    $channel->onUnsolicited(function (string $line) use (&$seen) { $seen[] = $line; });
    $loop->addTimer(0.01, fn () => $transport->feed("+CMTI: \"SM\",9\r\n")); // no prompt ever
    try {
        $channel->sendExpectingPrompt('AT+CMGS="+79161234567"', 'Hello');
    } catch (AtException) {
    }
    expect($seen)->toBe(['+CMTI: "SM",9']);
});
