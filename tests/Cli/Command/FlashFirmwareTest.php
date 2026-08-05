<?php

declare(strict_types=1);

use Femus\Cli\Arduino\ArduinoCli;
use Femus\Cli\Command\FlashFirmware;
use Femus\Cli\Process\CommandResult;
use Femus\Tests\Cli\FakeCommandRunner;
use Femus\Transport\SerialPortLocator;

function makeFlash(FakeCommandRunner $runner, array $ports): FlashFirmware
{
    $locator = new SerialPortLocator(fn (string $pattern): array => $ports);

    return new FlashFirmware(new ArduinoCli($runner), $locator, '/project');
}

it('rejects an unknown target with usage exit code 2', function () {
    $runner = new FakeCommandRunner();
    $lines = [];
    $out = static function (string $line) use (&$lines): void {
        $lines[] = $line;
    };
    $code = makeFlash($runner, [])->run('nope', [], $out);
    expect($code)->toBe(2)
        ->and($runner->calls)->toBe([]);
    expect(implode("\n", $lines))->toContain('usage');
});

it('fails with exit code 1 when arduino-cli is missing', function () {
    $runner = new FakeCommandRunner(new CommandResult(127));
    $code = makeFlash($runner, [])->run('radio-bridge', [], static fn (string $l) => null);
    expect($code)->toBe(1)
        ->and($runner->calls[0])->toBe(['arduino-cli', 'version']);
});

it('installs deps, resolves the auto port and flashes radio-bridge', function () {
    $runner = new FakeCommandRunner();
    $ports = PHP_OS_FAMILY === 'Darwin' ? ['/dev/cu.usbserial-1420'] : ['/dev/ttyUSB0'];
    $code = makeFlash($runner, $ports)->run('radio-bridge', [], static fn (string $l) => null);

    $expectedPort = $ports[0];
    expect($code)->toBe(0);
    expect($runner->calls)->toContain(['arduino-cli', 'core', 'install', 'arduino:avr']);
    expect($runner->calls)->toContain(['arduino-cli', 'lib', 'install', 'RadioHead']);
    expect(end($runner->calls))->toBe([
        'arduino-cli', 'compile',
        '--fqbn', 'arduino:avr:nano:cpu=atmega328old',
        '--upload',
        '-p', $expectedPort,
        '/project/firmware/RadioBleBridge',
    ]);
});

it('fails when no board is found on auto port', function () {
    $runner = new FakeCommandRunner();
    $code = makeFlash($runner, [])->run('femus', ['port' => 'auto'], static fn (string $l) => null);
    expect($code)->toBe(1);
    // compile must not have run
    foreach ($runner->calls as $call) {
        expect($call[1] ?? '')->not->toBe('compile');
    }
});

it('honors an explicit --fqbn and --port', function () {
    $runner = new FakeCommandRunner();
    $code = makeFlash($runner, [])->run('femus', ['port' => '/dev/ttyUSB9', 'fqbn' => 'arduino:avr:nano'], static fn (string $l) => null);
    expect($code)->toBe(0);
    expect(end($runner->calls))->toBe([
        'arduino-cli', 'compile',
        '--fqbn', 'arduino:avr:nano',
        '--upload',
        '-p', '/dev/ttyUSB9',
        '/project/firmware/FemusFirmata',
    ]);
});
