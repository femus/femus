<?php

declare(strict_types=1);

namespace Femus\Gsm\Gateway;

use Femus\Gsm\Sms;

/**
 * A personal SMS↔internet gateway: the box you keep at home. It receives an SMS,
 * checks the sender is allowed, routes it to a slash-command or (by default) the AI
 * agent, and texts the answer back. Long answers are split into several SMS.
 *
 * Wire it to a modem:
 *   $modem->onSmsReceived(fn (Sms $s) => $gateway->handle($s));
 * with an {@see SmsSender} that calls $modem->sendSms(). Both sides are injectable,
 * so the whole gateway is testable against fakes with no hardware or network.
 */
final class SmsGateway
{
    /** @var array<string, SmsCommand> */
    private array $commands = [];

    /**
     * @param list<SmsCommand> $commands
     * @param list<string> $allowedNumbers empty = open to everyone (testing); set it for a personal box
     */
    public function __construct(
        private readonly SmsSender $sender,
        private readonly AiClient $ai,
        array $commands = [],
        private readonly array $allowedNumbers = [],
        private readonly int $maxSmsLen = 150,
    ) {
        foreach ($commands as $command) {
            $this->commands[$command->name()] = $command;
        }
    }

    public function handle(Sms $message): void
    {
        if (!$this->isAllowed($message->from)) {
            return;
        }

        $reply = $this->route(trim($message->text), $message);

        // Human replies are split into plain chunks a normal phone reads in order —
        // NOT the headered packet format (that's only for femus↔femus, via SmsTransport).
        foreach ($this->splitForPhone($reply) as $part) {
            $this->sender->send($message->from, $part);
        }
    }

    /** @return list<string> */
    private function splitForPhone(string $text): array
    {
        if (mb_strlen($text) <= $this->maxSmsLen) {
            return [$text];
        }

        return mb_str_split($text, $this->maxSmsLen);
    }

    private function route(string $text, Sms $message): string
    {
        if (!str_starts_with($text, '/')) {
            return $this->ai->ask($text);
        }

        $parts = explode(' ', substr($text, 1), 2);
        $name = strtolower($parts[0]);
        $args = $parts[1] ?? '';

        $command = $this->commands[$name] ?? null;
        if ($command === null) {
            return "Unknown command '/{$name}'. Send /help for the list.";
        }

        return $command->handle($args, $message);
    }

    private function isAllowed(string $number): bool
    {
        return $this->allowedNumbers === [] || in_array($number, $this->allowedNumbers, true);
    }
}
