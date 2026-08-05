<?php

declare(strict_types=1);

namespace Femus\Cli\Process;

interface CommandRunner
{
    /** @param list<string> $argv */
    public function run(array $argv): CommandResult;
}
