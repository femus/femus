<?php

declare(strict_types=1);

use Femus\Gsm\Gateway\Command\PingCommand;
use Femus\Gsm\Gateway\SmsGateway;
use Femus\Gsm\Sms;
use Femus\Tests\Gsm\Gateway\FakeAiClient;
use Femus\Tests\Gsm\Gateway\RecordingSender;

function gateway(RecordingSender $sender, FakeAiClient $ai, array $allowed = []): SmsGateway
{
    return new SmsGateway($sender, $ai, commands: [new PingCommand()], allowedNumbers: $allowed);
}

it('routes a slash command to its handler', function () {
    $sender = new RecordingSender();
    gateway($sender, new FakeAiClient())->handle(new Sms('+1555', '/ping'));

    expect($sender->lastText())->toBe('pong')
        ->and($sender->sent[0]['to'])->toBe('+1555');
});

it('falls back to the AI agent for a plain-text question', function () {
    $sender = new RecordingSender();
    $ai = new FakeAiClient('It is 21°C in Halifax.');
    gateway($sender, $ai)->handle(new Sms('+1555', 'weather in halifax?'));

    expect($ai->questions)->toBe(['weather in halifax?'])
        ->and($sender->lastText())->toBe('It is 21°C in Halifax.');
});

it('answers an unknown command with a hint', function () {
    $sender = new RecordingSender();
    gateway($sender, new FakeAiClient())->handle(new Sms('+1555', '/bogus'));

    expect($sender->lastText())->toContain('/help');
});

it('serves only whitelisted numbers when a whitelist is set', function () {
    $sender = new RecordingSender();
    $ai = new FakeAiClient();
    gateway($sender, $ai, allowed: ['+1999'])->handle(new Sms('+1555', 'hello'));

    expect($sender->sent)->toBe([])
        ->and($ai->questions)->toBe([]);
});

it('serves a whitelisted number', function () {
    $sender = new RecordingSender();
    gateway($sender, new FakeAiClient('hi'), allowed: ['+1999'])->handle(new Sms('+1999', 'hello'));

    expect($sender->lastText())->toBe('hi');
});

it('serves everyone when no whitelist is configured (open mode)', function () {
    $sender = new RecordingSender();
    gateway($sender, new FakeAiClient('hi'))->handle(new Sms('+1anyone', 'hello'));

    expect($sender->lastText())->toBe('hi');
});

it('sends a long AI answer as multiple SMS parts', function () {
    $sender = new RecordingSender();
    $long = str_repeat('word ', 100); // 500 chars
    gateway($sender, new FakeAiClient($long))->handle(new Sms('+1555', 'tell me a lot'));

    expect(count($sender->sent))->toBeGreaterThan(1);
});
