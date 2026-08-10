<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Gsm\Gateway\AiClient;
use Femus\Gsm\Gateway\ClaudeAiClient;
use Femus\Gsm\Gateway\Command\HelpCommand;
use Femus\Gsm\Gateway\Command\PingCommand;
use Femus\Gsm\Gateway\ModemSender;
use Femus\Gsm\Gateway\SmsGateway;
use Femus\Gsm\GsmModem;
use Femus\Gsm\Sms;

// Personal SMS↔internet gateway — the box you keep at home.
// Text it from any phone (no data needed) and it texts an answer back.
//
// Usage: ANTHROPIC_API_KEY=sk-... php examples/sms-gateway.php /dev/ttyUSB0

$port = $argv[1] ?? null;

// Claude answers plain-text questions when ANTHROPIC_API_KEY is set; otherwise a
// stub replies so you can test the wiring without a key.
$apiKey = getenv('ANTHROPIC_API_KEY') ?: '';
$ai = $apiKey !== ''
    ? new ClaudeAiClient($apiKey)
    : new class implements AiClient {
        public function ask(string $question): string
        {
            return "You asked: {$question}. (Set ANTHROPIC_API_KEY for real answers.)";
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
