<?php

declare(strict_types=1);

namespace Femus\Gsm\Gateway\Command;

use Femus\Gsm\Gateway\SmsCommand;
use Femus\Gsm\Sms;

/** Lists the available commands. Built from the gateway's command set. */
final class HelpCommand implements SmsCommand
{
    /** @param list<SmsCommand> $commands */
    public function __construct(private array $commands = [])
    {
    }

    /** @param list<SmsCommand> $commands */
    public function withCommands(array $commands): self
    {
        $this->commands = $commands;

        return $this;
    }

    public function name(): string
    {
        return 'help';
    }

    public function description(): string
    {
        return 'list commands';
    }

    public function handle(string $args, Sms $message): string
    {
        $lines = ['Commands (or just ask a question):'];
        foreach ($this->commands as $command) {
            $lines[] = '/' . $command->name() . ' — ' . $command->description();
        }

        return implode("\n", $lines);
    }
}
