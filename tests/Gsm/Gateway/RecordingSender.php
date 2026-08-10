<?php

declare(strict_types=1);

namespace Femus\Tests\Gsm\Gateway;

use Femus\Gsm\Gateway\SmsSender;

/** Test double: records every outgoing SMS instead of hitting a modem. */
final class RecordingSender implements SmsSender
{
    /** @var list<array{to: string, text: string}> */
    public array $sent = [];

    public function send(string $to, string $text): void
    {
        $this->sent[] = ['to' => $to, 'text' => $text];
    }

    public function lastText(): ?string
    {
        return $this->sent === [] ? null : end($this->sent)['text'];
    }
}
