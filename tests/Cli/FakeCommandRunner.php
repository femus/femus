<?php

declare(strict_types=1);

namespace Femus\Tests\Cli;

use Femus\Cli\Process\CommandResult;
use Femus\Cli\Process\CommandRunner;

final class FakeCommandRunner implements CommandRunner
{
    /** @var list<list<string>> */
    public array $calls = [];

    /** @param array<int, CommandResult> $results keyed by call index */
    public function __construct(
        private readonly CommandResult $default = new CommandResult(0),
        private readonly array $results = [],
    ) {
    }

    public function run(array $argv): CommandResult
    {
        $index = count($this->calls);
        $this->calls[] = $argv;

        return $this->results[$index] ?? $this->default;
    }
}
