<?php

declare(strict_types=1);

namespace Femus\Cli\Command;

use Femus\Transport\SerialPortLocator;

final class ScanPorts
{
    /** @var array<string, string> status → human-readable line suffix */
    private const LABELS = [
        'firmata' => 'Firmata board — femus-ready',
        'silent' => 'no response — flash it: femus firmware:flash femus',
        'busy' => 'in use — close Arduino IDE or another program using it',
    ];

    /** @param callable(string): string $prober maps a port to a status: firmata|silent|busy */
    public function __construct(
        private readonly SerialPortLocator $locator,
        private $prober,
    ) {
    }

    /** @param callable(string): void $out */
    public function run(callable $out): int
    {
        $ports = $this->locator->candidates();

        if ($ports === []) {
            $out('No serial ports found. Connect a board over USB and try again.');

            return 0;
        }

        $out(sprintf('Found %d serial port%s:', count($ports), count($ports) === 1 ? '' : 's'));
        foreach ($ports as $port) {
            $status = ($this->prober)($port);
            $label = self::LABELS[$status] ?? self::LABELS['silent'];
            $mark = $status === 'firmata' ? '✓' : '·';
            $out(sprintf('  %s %s — %s', $mark, $port, $label));
        }

        return 0;
    }
}
