<?php

declare(strict_types=1);

use Femus\Adapter\Linux\LinuxDigitalPin;
use Femus\Cli\Process\CommandResult;
use Femus\Contracts\PinMode;
use Femus\Runtime\StreamSelectLoop;
use Femus\Tests\Cli\FakeCommandRunner;

it('configures the pin mode via pinctrl on construction', function () {
    $runner = new FakeCommandRunner();
    new LinuxDigitalPin($runner, 17, PinMode::Output, new StreamSelectLoop());
    expect($runner->calls[0])->toBe(['pinctrl', 'set', '17', 'op']);
});

it('sets an input pull-up', function () {
    $runner = new FakeCommandRunner();
    new LinuxDigitalPin($runner, 4, PinMode::InputPullUp, new StreamSelectLoop());
    expect($runner->calls[0])->toBe(['pinctrl', 'set', '4', 'ip', 'pu']);
});

it('drives the line high and low', function () {
    $runner = new FakeCommandRunner();
    $pin = new LinuxDigitalPin($runner, 17, PinMode::Output, new StreamSelectLoop());

    $pin->write(true);
    expect(end($runner->calls))->toBe(['pinctrl', 'set', '17', 'dh']);

    $pin->write(false);
    expect(end($runner->calls))->toBe(['pinctrl', 'set', '17', 'dl']);
});

it('reads the line level by parsing pinctrl get', function () {
    $runner = new FakeCommandRunner(results: [
        1 => new CommandResult(0, '17: ip    -- pu | hi // GPIO17 = input'),
        2 => new CommandResult(0, '17: ip    -- pu | lo // GPIO17 = input'),
    ]);
    $pin = new LinuxDigitalPin($runner, 17, PinMode::Input, new StreamSelectLoop());

    expect($pin->read())->toBeTrue()
        ->and($pin->read())->toBeFalse();
});
