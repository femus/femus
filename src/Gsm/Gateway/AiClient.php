<?php

declare(strict_types=1);

namespace Femus\Gsm\Gateway;

/**
 * Answers a natural-language question. The production implementation calls the
 * Claude API over the box's internet connection; tests use a fake.
 */
interface AiClient
{
    public function ask(string $question): string;
}
