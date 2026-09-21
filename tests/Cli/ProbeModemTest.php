<?php

declare(strict_types=1);

use Femus\Cli\Command\ProbeModem;
use Femus\Gsm\AtResponse;
use Femus\Gsm\ModemProbe;
use Femus\Transport\SerialPortLocator;

function probeOutput(ProbeModem $command, ?string $port): array
{
    $lines = [];
    $code = $command->run($port, function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    return [$code, implode("\n", $lines)];
}

it('prints the baud it found and the report', function () {
    $tried = [];
    $command = new ProbeModem(
        new SerialPortLocator(),
        function (string $port, int $baud) use (&$tried): ?ModemProbe {
            $tried[] = $baud;

            // pretend only 9600 answers — the cheap 2G boards often do
            return $baud === 9600
                ? new ModemProbe(fn (string $c) => new AtResponse(true, $c === 'ATI' ? ['Model: SIM800L'] : []))
                : null;
        },
    );

    [$code, $output] = probeOutput($command, __FILE__);   // any existing path stands in for a port

    expect($code)->toBe(0)
        ->and($output)->toContain('@ 9600 baud')
        ->and($output)->toContain('SIM800L')
        ->and($output)->toContain('docs/hardware-runs.md')
        ->and($tried)->toBe([115200, 9600]);              // stops at the first one that answers
});

it('turns silence into a wiring checklist', function () {
    $command = new ProbeModem(new SerialPortLocator(), fn () => null);

    [$code, $output] = probeOutput($command, __FILE__);

    expect($code)->toBe(1)
        ->and($output)->toContain('silence at every baud rate')
        ->and($output)->toContain('GND shared')
        ->and($output)->toContain('TX and RX crossed');
});

it('tells an unplugged cable apart from a wiring fault', function () {
    $command = new ProbeModem(new SerialPortLocator(), fn () => null);

    [$code, $output] = probeOutput($command, '/dev/cu.nothing-here');

    expect($code)->toBe(1)
        ->and($output)->toContain('no such port')
        ->and($output)->not->toContain('silence at every baud');
});
