<?php

declare(strict_types=1);

namespace Femus\Gsm;

final readonly class Sms
{
    public function __construct(
        public string $from,
        public string $text,
    ) {
    }
}
