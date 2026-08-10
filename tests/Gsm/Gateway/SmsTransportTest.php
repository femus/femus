<?php

declare(strict_types=1);

use Femus\Gsm\Gateway\SmsReassembler;
use Femus\Gsm\Gateway\SmsTransport;

it('keeps a short payload as a single header-less segment', function () {
    $parts = (new SmsTransport())->chunk('hello', 'a1', maxLen: 150);
    expect($parts)->toBe(['hello']);
});

it('splits a long payload into headered segments', function () {
    $payload = str_repeat('X', 400);
    $parts = (new SmsTransport())->chunk($payload, 'a1', maxLen: 150);

    expect(count($parts))->toBeGreaterThan(1)
        ->and($parts[0])->toStartWith('[a1:1/')
        ->and(implode('', array_map(fn ($p) => preg_replace('/^\[[^\]]+\]/', '', $p), $parts)))->toBe($payload);
});

it('reassembles multi-part segments in order', function () {
    $transport = new SmsTransport();
    $payload = str_repeat('AB', 300);
    $parts = $transport->chunk($payload, 'msg', maxLen: 150);

    $r = new SmsReassembler();
    $result = null;
    foreach ($parts as $p) {
        $result = $r->add($p);
    }
    expect($result)->toBe($payload);
});

it('reassembles segments arriving out of order', function () {
    $transport = new SmsTransport();
    $payload = str_repeat('Z', 500);
    $parts = $transport->chunk($payload, 'm', maxLen: 150);
    $shuffled = array_reverse($parts);

    $r = new SmsReassembler();
    $result = null;
    foreach ($shuffled as $p) {
        $out = $r->add($p);
        if ($out !== null) {
            $result = $out;
        }
    }
    expect($result)->toBe($payload);
});

it('passes a header-less segment straight through as standalone', function () {
    expect((new SmsReassembler())->add('just a normal sms'))->toBe('just a normal sms');
});
