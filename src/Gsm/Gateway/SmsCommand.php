<?php

declare(strict_types=1);

namespace Femus\Gsm\Gateway;

use Femus\Gsm\Sms;

/** A slash-command handler, e.g. "/ping" or "/help". */
interface SmsCommand
{
    /** The command word without the leading slash, e.g. "ping". */
    public function name(): string;

    /** One-line description for the help listing. */
    public function description(): string;

    /** Handle the command; $args is the text after the command word. Returns the reply. */
    public function handle(string $args, Sms $message): string;
}
