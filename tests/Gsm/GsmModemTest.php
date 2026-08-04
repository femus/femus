<?php

declare(strict_types=1);

namespace Tests\Gsm;

use Femus\Gsm\AtChannel;
use Femus\Gsm\AtException;
use Femus\Gsm\GsmModem;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\InMemoryTransport;

function gsmModem(): array
{
    $transport = new InMemoryTransport();
    $loop = new StreamSelectLoop();
    $modem = new GsmModem(new AtChannel($transport, $loop, 1.0));

    return [$modem, $transport, $loop];
}

it('reports network registration from CREG', function () {
    [$modem, $transport, $loop] = gsmModem();
    $loop->addTimer(0.01, fn () => $transport->feed("+CREG: 0,1\r\nOK\r\n"));
    expect($modem->isRegistered())->toBeTrue();
    $loop->addTimer(0.01, fn () => $transport->feed("+CREG: 0,2\r\nOK\r\n"));
    expect($modem->isRegistered())->toBeFalse();
});

it('parses signal quality and maps 99 to null', function () {
    [$modem, $transport, $loop] = gsmModem();
    $loop->addTimer(0.01, fn () => $transport->feed("+CSQ: 21,0\r\nOK\r\n"));
    expect($modem->signalQuality())->toBe(21);
    $loop->addTimer(0.01, fn () => $transport->feed("+CSQ: 99,99\r\nOK\r\n"));
    expect($modem->signalQuality())->toBeNull();
});

it('sends an sms through the prompt flow', function () {
    [$modem, $transport, $loop] = gsmModem();
    $loop->addTimer(0.01, fn () => $transport->feed("\r\n> "));
    $loop->addTimer(0.03, fn () => $transport->feed("+CMGS: 4\r\nOK\r\n"));
    $modem->sendSms('+79161234567', 'Hello');
    expect($transport->written)->toBe("AT+CMGS=\"+79161234567\"\rHello\x1A");
});

it('reads a multiline sms', function () {
    [$modem, $transport, $loop] = gsmModem();
    $loop->addTimer(0.01, fn () => $transport->feed(
        "+CMGR: \"REC UNREAD\",\"+79161234567\",,\"26/08/04,12:00:00+12\"\r\nfirst line\r\nsecond line\r\nOK\r\n",
    ));
    $sms = $modem->readSms(3);
    expect($sms->from)->toBe('+79161234567')
        ->and($sms->text)->toBe("first line\nsecond line");
});

it('emits Sms objects on CMTI notifications', function () {
    [$modem, $transport, $loop] = gsmModem();
    $received = null;
    $modem->onSmsReceived(function ($sms) use (&$received, $loop) {
        $received = $sms;
        $loop->stop();
    });
    // CMTI arrives while idle; the handler then issues AT+CMGR=3 — answer it a moment later
    $loop->addTimer(0.01, fn () => $transport->feed("\r\n+CMTI: \"SM\",3\r\n"));
    $loop->addTimer(0.05, fn () => $transport->feed(
        "+CMGR: \"REC UNREAD\",\"+79161234567\",,\"26/08/04,12:00:00+12\"\r\nPing\r\nOK\r\n",
    ));
    $loop->addTimer(1.0, fn () => $loop->stop());
    $loop->run();
    expect($received)->not->toBeNull()
        ->and($received->from)->toBe('+79161234567')
        ->and($received->text)->toBe('Ping')
        ->and($transport->written)->toContain('AT+CMGR=3');
});

it('init sends the setup sequence and fails loudly on rejection', function () {
    [$modem, $transport, $loop] = gsmModem();
    $loop->addTimer(0.01, fn () => $transport->feed("OK\r\n"));
    $loop->addTimer(0.03, fn () => $transport->feed("OK\r\n"));
    $loop->addTimer(0.05, fn () => $transport->feed("OK\r\n"));
    $modem->init();
    expect($transport->written)->toBe("ATE0\rAT+CMGF=1\rAT+CNMI=2,1,0,0,0\r");
});
