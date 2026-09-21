<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Gsm\Gateway\AiClient;
use Femus\Gsm\Gateway\ClaudeAiClient;
use Femus\Gsm\Gateway\Command\HelpCommand;
use Femus\Gsm\Gateway\Command\PingCommand;
use Femus\Gsm\Gateway\Command\WeatherCommand;
use Femus\Gsm\Gateway\ModemSender;
use Femus\Gsm\Gateway\OpenAiCompatibleAiClient;
use Femus\Gsm\Gateway\SmsGateway;
use Femus\Gsm\Gateway\SmsSender;
use Femus\Gsm\GsmModem;
use Femus\Gsm\Sms;

// Personal SMS↔internet gateway — the box you keep at home.
// Text it from any phone (no data needed) and it texts an answer back.
//
// Usage: ANTHROPIC_API_KEY=sk-... php examples/sms-gateway.php /dev/ttyUSB0
//    or: GEMINI_API_KEY=AIza... php examples/sms-gateway.php /dev/ttyUSB0   (free tier)

$port = $argv[1] ?? null;

// Whichever key is in the environment answers the questions; with none, a stub
// replies so you can test the wiring without an account.
$ai = match (true) {
    (bool) getenv('ANTHROPIC_API_KEY') => new ClaudeAiClient((string) getenv('ANTHROPIC_API_KEY')),
    (bool) getenv('GEMINI_API_KEY') => new OpenAiCompatibleAiClient((string) getenv('GEMINI_API_KEY')),
    default => new class implements AiClient {
        public function ask(string $question): string
        {
            return "You asked: {$question}. (Set ANTHROPIC_API_KEY or GEMINI_API_KEY for real answers.)";
        }
    },
};

$modem = GsmModem::open($port);
$modem->init();

$help = new HelpCommand();
$commands = [new PingCommand(), new WeatherCommand(), $help];
$help->withCommands($commands);

// Printing both sides makes the box legible while it works — and doubles as the
// narration when you film the thing.
$sender = new class(new ModemSender($modem)) implements SmsSender {
    public function __construct(private readonly SmsSender $inner)
    {
    }

    public function send(string $to, string $text): void
    {
        fwrite(STDOUT, sprintf("[%s] → %s\n", date('H:i:s'), $text));
        $this->inner->send($to, $text);
    }
};

$gateway = new SmsGateway(
    $sender,
    $ai,
    commands: $commands,
    // Your own / family numbers, kept out of the repo:
    //   SMS_ALLOWED=+15551234567,+15557654321 php examples/sms-gateway.php <port>
    // An empty list serves everyone, which is not recommended — it is your API bill.
    allowedNumbers: array_filter(array_map('trim', explode(',', (string) getenv('SMS_ALLOWED')))),
);

$modem->onSmsReceived(function (Sms $sms) use ($gateway): void {
    // last four digits only: the log ends up on screenshots and in demo videos
    fwrite(STDOUT, sprintf("[%s] ← …%s: %s\n", date('H:i:s'), substr($sms->from, -4), $sms->text));
    $gateway->handle($sms);
});

fwrite(STDOUT, "SMS gateway running. Text the SIM's number from any phone. Ctrl+C to quit.\n");
$modem->run();
