<?php

declare(strict_types=1);

namespace Femus\Cli\Command;

use Femus\Cli\Arduino\ArduinoCli;
use Femus\Transport\SerialPortLocator;

final class FlashFirmware
{
    private const DEFAULT_FQBN = 'arduino:avr:nano:cpu=atmega328old';

    /** @var array<string, array{dir: string, hex: string, libs: list<string>}> */
    private const TARGETS = [
        'femus' => [
            'dir' => 'firmware/FemusFirmata',
            'hex' => 'firmware/build/FemusFirmata.ino.hex',
            'libs' => ['ConfigurableFirmata', 'RadioHead', 'HX711'],
        ],
        'radio-bridge' => [
            'dir' => 'firmware/RadioBleBridge',
            'hex' => 'firmware/build/RadioBleBridge.ino.hex',
            'libs' => ['RadioHead'],
        ],
    ];

    public function __construct(
        private readonly ArduinoCli $arduino,
        private readonly SerialPortLocator $locator,
        private readonly string $projectRoot,
    ) {
    }

    /**
     * @param array<string, string> $options
     * @param callable(string): void $out
     */
    public function run(?string $target, array $options, callable $out): int
    {
        if ($target === null || !isset(self::TARGETS[$target])) {
            $out('usage: femus firmware:flash <femus|radio-bridge> [--port=auto] [--fqbn=...] [--build]');

            return 2;
        }

        if (!$this->arduino->isAvailable()) {
            $out('arduino-cli not found. Install it: https://arduino.github.io/arduino-cli/latest/installation/');

            return 1;
        }

        $spec = self::TARGETS[$target];
        $fqbn = $options['fqbn'] ?? self::DEFAULT_FQBN;

        $port = $options['port'] ?? 'auto';
        if ($port === 'auto') {
            $candidates = $this->locator->candidates();
            if ($candidates === []) {
                $out('No board found. Connect it or pass --port=/dev/...');

                return 1;
            }
            $port = $candidates[0];
        }
        $out("Port: {$port}");

        if (!$this->arduino->coreInstall('arduino:avr')->succeeded()) {
            $out('Failed to install core arduino:avr.');

            return 1;
        }

        $hexFile = $this->projectRoot . '/' . $spec['hex'];
        if (!array_key_exists('build', $options) && is_file($hexFile)) {
            $out("Flashing {$target} ({$fqbn}, prebuilt hex) ...");

            if (!$this->arduino->uploadHex($hexFile, $fqbn, $port)->succeeded()) {
                $out('Flash failed.');

                return 1;
            }

            $out('Done.');

            return 0;
        }

        foreach ($spec['libs'] as $lib) {
            if (!$this->arduino->libInstall($lib)->succeeded()) {
                $out("Failed to install library: {$lib}");

                return 1;
            }
        }

        $sketchDir = $this->projectRoot . '/' . $spec['dir'];
        $out("Flashing {$target} ({$fqbn}, from source) ...");

        if (!$this->arduino->compileAndUpload($sketchDir, $fqbn, $port)->succeeded()) {
            $out('Flash failed.');

            return 1;
        }

        $out('Done.');

        return 0;
    }
}
