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

    public function uploadHex(string $hexFile, string $fqbn, string $port): CommandResult
    {
        return $this->runner->run([
            $this->binary, 'upload',
            '--fqbn', $fqbn,
            '-p', $port,
            '--input-file', $hexFile,
        ]);
    }

    /**
     * Upload a hex with the avrdude on PATH instead of the one arduino-cli bundles.
     * The bundled one is an Intel build: on an Apple Silicon Mac without Rosetta it dies with "bad CPU type".
     */
    public function uploadHexWithSystemAvrdude(string $hexFile, string $fqbn, string $port): CommandResult
    {
        // ponytail: assumes an ATmega328 board (Nano, Uno); map fqbn to mcu when another board shows up
        $baud = str_contains($fqbn, 'atmega328old') ? '57600' : '115200';

        return $this->runner->run([
            'avrdude', '-c', 'arduino', '-p', 'm328p',
            '-P', $port, '-b', $baud,
            '-U', "flash:w:{$hexFile}:i",
        ]);
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
