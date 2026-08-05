<?php

declare(strict_types=1);

namespace Femus\Cli\Arduino;

use Femus\Cli\Process\CommandResult;
use Femus\Cli\Process\CommandRunner;

final class ArduinoCli
{
    public function __construct(
        private readonly CommandRunner $runner,
        private readonly string $binary = 'arduino-cli',
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->runner->run([$this->binary, 'version'])->succeeded();
    }

    public function coreInstall(string $core): CommandResult
    {
        return $this->runner->run([$this->binary, 'core', 'install', $core]);
    }

    public function libInstall(string $lib): CommandResult
    {
        return $this->runner->run([$this->binary, 'lib', 'install', $lib]);
    }

    public function compileAndUpload(string $sketchDir, string $fqbn, string $port): CommandResult
    {
        return $this->runner->run([
            $this->binary, 'compile',
            '--fqbn', $fqbn,
            '--upload',
            '-p', $port,
            $sketchDir,
        ]);
    }
}
