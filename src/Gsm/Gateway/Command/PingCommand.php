<?php

declare(strict_types=1);

namespace Femus\Gsm\Gateway\Command;

use Femus\Gsm\Gateway\SmsCommand;
use Femus\Gsm\Sms;

/** Liveness check: "/ping" -> "pong". */
final class PingCommand implements SmsCommand
{
    public function name(): string
    {
        return 'ping';
    }

    public function description(): string
    {
        return 'check the gateway is alive';
    }

    public function handle(string $args, Sms $message): string
    {
        return 'pong';
    }
}
