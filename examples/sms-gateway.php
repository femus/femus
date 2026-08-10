<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Gsm\Gateway\AiClient;
use Femus\Gsm\Gateway\Command\HelpCommand;
use Femus\Gsm\Gateway\Command\PingCommand;
use Femus\Gsm\Gateway\ModemSender;
use Femus\Gsm\Gateway\SmsGateway;
use Femus\Gsm\GsmModem;
use Femus\Gsm\Sms;

// Personal SMS↔internet gateway — the box you keep at home.
// Text it from any phone (no data needed) and it texts an answer back.
//
// Usage: php examples/sms-gateway.php /dev/ttyUSB0
//
// This is a scaffold: the AI client below is a stub. Replace it with a real
// Claude API client (needs an API key and the box's internet) to get answers.

$port = $argv[1] ?? null;

// TODO: replace with a real Claude-backed client. Keep answers short (~1–2 SMS).
$ai = new class implements AiClient {
    public function ask(string $question): string
    {
        return "You asked: {$question}. (Connect a real AI client to answer.)";
    }
};

$modem = GsmModem::open($port);
$modem->init();

$help = new HelpCommand();
$commands = [new PingCommand(), $help];
$help->withCommands($commands);

$gateway = new SmsGateway(
    new ModemSender($modem),
    $ai,
    commands: $commands,
    allowedNumbers: [
        // Your own / family numbers. Leave empty to serve everyone (not recommended).
        // '+15551234567',
    ],
);

$modem->onSmsReceived(fn (Sms $sms) => $gateway->handle($sms));

fwrite(STDOUT, "SMS gateway running. Text the SIM's number from any phone. Ctrl+C to quit.\n");
$modem->run();
