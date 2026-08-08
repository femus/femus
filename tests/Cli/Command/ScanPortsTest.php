<?php

declare(strict_types=1);

use Femus\Cli\Command\ScanPorts;
use Femus\Transport\SerialPortLocator;

function makeScan(array $ports, array $statuses): ScanPorts
{
    $locator = new SerialPortLocator(fn (string $pattern): array => $ports);
    $prober = fn (string $port): string => $statuses[$port] ?? 'silent';

    return new ScanPorts($locator, $prober);
}

function scanLines(ScanPorts $scan): array
{
    $lines = [];
    $code = $scan->run(function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    return [$code, implode("\n", $lines)];
}

it('reports when no ports are found', function () {
    [$code, $text] = scanLines(makeScan([], []));
    expect($code)->toBe(0)
        ->and($text)->toContain('No serial ports found');
});

it('marks a Firmata board as femus-ready', function () {
    [$code, $text] = scanLines(makeScan(['/dev/ttyUSB0'], ['/dev/ttyUSB0' => 'firmata']));
    expect($code)->toBe(0)
        ->and($text)->toContain('/dev/ttyUSB0')
        ->and($text)->toContain('femus-ready');
});

it('suggests flashing when a port is silent', function () {
    [, $text] = scanLines(makeScan(['/dev/ttyUSB0'], ['/dev/ttyUSB0' => 'silent']));
    expect($text)->toContain('firmware:flash');
});

it('explains a busy port in human terms', function () {
    [, $text] = scanLines(makeScan(['/dev/ttyUSB0'], ['/dev/ttyUSB0' => 'busy']));
    expect($text)->toContain('in use');
});

it('lists every candidate port', function () {
    [, $text] = scanLines(makeScan(
        ['/dev/ttyUSB0', '/dev/ttyUSB1'],
        ['/dev/ttyUSB0' => 'firmata', '/dev/ttyUSB1' => 'silent'],
    ));
    expect($text)->toContain('/dev/ttyUSB0')
        ->and($text)->toContain('/dev/ttyUSB1');
});
