<?php

declare(strict_types=1);

use Femus\Cli\Arduino\ArduinoCli;
use Femus\Cli\Command\FlashFirmware;
use Femus\Cli\Process\CommandResult;
use Femus\Tests\Cli\FakeCommandRunner;
use Femus\Transport\SerialPortLocator;

function makeFlash(FakeCommandRunner $runner, array $ports, string $projectRoot = '/project'): FlashFirmware
{
    $locator = new SerialPortLocator(fn (string $pattern): array => $ports);

    return new FlashFirmware(new ArduinoCli($runner), $locator, $projectRoot);
}

function makeRootWithHex(string $hexName): string
{
    $root = sys_get_temp_dir() . '/femus-flash-' . bin2hex(random_bytes(4));
    mkdir($root . '/firmware/build', 0777, true);
    file_put_contents($root . '/firmware/build/' . $hexName, ':00000001FF');

    return $root;
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

it('uploads a bundled hex without installing libraries or compiling', function () {
    $runner = new FakeCommandRunner();
    $root = makeRootWithHex('RadioBleBridge.ino.hex');
    $code = makeFlash($runner, [], $root)->run('radio-bridge', ['port' => '/dev/ttyUSB9'], static fn (string $l) => null);

    expect($code)->toBe(0);
    expect(end($runner->calls))->toBe([
        'arduino-cli', 'upload',
        '--fqbn', 'arduino:avr:nano:cpu=atmega328old',
        '-p', '/dev/ttyUSB9',
        '--input-file', $root . '/firmware/build/RadioBleBridge.ino.hex',
    ]);
    foreach ($runner->calls as $call) {
        expect($call[1] ?? '')->not->toBe('compile')
            ->and($call[1] ?? '')->not->toBe('lib');
    }
});

it('compiles from source when --build is passed despite a bundled hex', function () {
    $runner = new FakeCommandRunner();
    $root = makeRootWithHex('FemusFirmata.ino.hex');
    $code = makeFlash($runner, [], $root)->run('femus', ['port' => '/dev/ttyUSB9', 'build' => ''], static fn (string $l) => null);

    expect($code)->toBe(0);
    expect($runner->calls)->toContain(['arduino-cli', 'lib', 'install', 'RadioHead']);
    expect(end($runner->calls))->toBe([
        'arduino-cli', 'compile',
        '--fqbn', 'arduino:avr:nano:cpu=atmega328old',
        '--upload',
        '-p', '/dev/ttyUSB9',
        $root . '/firmware/FemusFirmata',
    ]);
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
