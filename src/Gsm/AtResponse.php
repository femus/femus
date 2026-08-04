<?php

declare(strict_types=1);

namespace Femus\Gsm;

final class AtResponse
{
    /** @param list<string> $lines */
    public function __construct(
        public readonly bool $ok,
        public readonly array $lines,
    ) {
    }

    public function firstLine(): ?string
    {
        return $this->lines[0] ?? null;
    }
}
