<?php

declare(strict_types=1);

namespace Femus\Contracts;

final readonly class RadioMessage
{
    public function __construct(
        public int $from,
        public int $to,
        public string $message,
    ) {
    }
}
