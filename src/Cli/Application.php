<?php

declare(strict_types=1);

namespace Femus\Cli;

use Femus\Cli\Arduino\ArduinoCli;
use Femus\Cli\Command\FlashFirmware;
use Femus\Cli\Command\FlashOptions;
use Femus\Cli\Process\SystemCommandRunner;
use Femus\Transport\SerialPortLocator;

final class Application
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @param list<string> $argv full argv, script name at [0]
     * @param callable(string): void $out
     */
    public function run(array $argv, callable $out): int
    {
        $command = $argv[1] ?? null;

        if ($command === 'firmware:flash') {
            $parsed = FlashOptions::parse(array_slice($argv, 2));
            $flash = new FlashFirmware(
                new ArduinoCli(new SystemCommandRunner()),
                new SerialPortLocator(),
                $this->projectRoot,
            );

            return $flash->run($parsed->target, $parsed->options, $out);
        }

        $out('femus — PHP hardware framework CLI');
        $out('usage: femus firmware:flash <femus|radio-bridge> [--port=auto] [--fqbn=...]');

        return $command === null ? 0 : 2;
    }
}
