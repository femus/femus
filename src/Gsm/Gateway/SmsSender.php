<?php

declare(strict_types=1);

namespace Femus\Gsm\Gateway;

/** Sends an outgoing SMS. GsmModem satisfies this; tests use a recording fake. */
interface SmsSender
{
    public function send(string $to, string $text): void;
}
