<?php

declare(strict_types=1);

namespace Femus\Cli\Process;

final class CommandResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output = '',
    ) {
    }

    public function succeeded(): bool
    {
        return $this->exitCode === 0;
    }
}
