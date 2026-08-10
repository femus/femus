<?php

declare(strict_types=1);

namespace Femus\Gsm\Gateway;

use Femus\Gsm\GsmModem;

/** Adapts a {@see GsmModem} to the {@see SmsSender} interface the gateway expects. */
final class ModemSender implements SmsSender
{
    public function __construct(private readonly GsmModem $modem)
    {
    }

    public function send(string $to, string $text): void
    {
        $this->modem->sendSms($to, $text);
    }
}
