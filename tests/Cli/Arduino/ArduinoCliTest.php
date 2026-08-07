<?php

declare(strict_types=1);

use Femus\Cli\Arduino\ArduinoCli;
use Femus\Cli\Process\CommandResult;
use Femus\Tests\Cli\FakeCommandRunner;

it('reports availability from the version subcommand exit code', function () {
    $ok = new ArduinoCli(new FakeCommandRunner(new CommandResult(0)));
    $missing = new ArduinoCli(new FakeCommandRunner(new CommandResult(127)));
    expect($ok->isAvailable())->toBeTrue()
        ->and($missing->isAvailable())->toBeFalse();
});

it('builds the compile-and-upload argv', function () {
    $runner = new FakeCommandRunner();
    (new ArduinoCli($runner))->compileAndUpload('firmware/RadioBleBridge', 'arduino:avr:nano:cpu=atmega328old', '/dev/cu.usbserial-1420');
    expect($runner->calls[0])->toBe([
        'arduino-cli', 'compile',
        '--fqbn', 'arduino:avr:nano:cpu=atmega328old',
        '--upload',
        '-p', '/dev/cu.usbserial-1420',
        'firmware/RadioBleBridge',
    ]);
});

it('builds the prebuilt hex upload argv', function () {
    $runner = new FakeCommandRunner();
    (new ArduinoCli($runner))->uploadHex('firmware/build/RadioBleBridge.ino.hex', 'arduino:avr:nano:cpu=atmega328old', '/dev/cu.usbserial-1420');
    expect($runner->calls[0])->toBe([
        'arduino-cli', 'upload',
        '--fqbn', 'arduino:avr:nano:cpu=atmega328old',
        '-p', '/dev/cu.usbserial-1420',
        '--input-file', 'firmware/build/RadioBleBridge.ino.hex',
    ]);
});

it('builds core and lib install argv', function () {
    $runner = new FakeCommandRunner();
    $cli = new ArduinoCli($runner);
    $cli->coreInstall('arduino:avr');
    $cli->libInstall('RadioHead');
    expect($runner->calls[0])->toBe(['arduino-cli', 'core', 'install', 'arduino:avr'])
        ->and($runner->calls[1])->toBe(['arduino-cli', 'lib', 'install', 'RadioHead']);
});
