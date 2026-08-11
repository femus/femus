<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeOneWireReader;
use Femus\Adapter\Linux\LinuxBoard;
use Femus\Device\Ds18b20;
use Femus\Tests\Cli\FakeCommandRunner;

it('parses a temperature from a w1_slave reading', function () {
    $bus = new FakeOneWireReader();
    $bus->setRaw('28-abc', "ce 01 4b 46 7f ff 0c 10 6e : crc=6e YES\nce 01 4b 46 7f ff 0c 10 6e t=28875\n");
    $sensor = new Ds18b20($bus, '28-abc');

    expect($sensor->celsius())->toBe(28.875);
});

it('converts to fahrenheit', function () {
    $bus = new FakeOneWireReader();
    $bus->setCelsius('28-abc', 25.0);
    $sensor = new Ds18b20($bus, '28-abc');

    expect($sensor->fahrenheit())->toBe(77.0);
});

it('reads negative temperatures', function () {
    $bus = new FakeOneWireReader();
    $bus->setCelsius('28-abc', -7.5);
    $sensor = new Ds18b20($bus, '28-abc');

    expect($sensor->celsius())->toBe(-7.5);
});

it('returns null on a bad CRC', function () {
    $bus = new FakeOneWireReader();
    $bus->setBadCrc('28-abc');
    $sensor = new Ds18b20($bus, '28-abc');

    expect($sensor->celsius())->toBeNull()
        ->and($sensor->fahrenheit())->toBeNull();
});

it('picks the first sensor on the bus by default', function () {
    $bus = new FakeOneWireReader();
    $bus->setCelsius('28-first', 21.0);
    $bus->setCelsius('28-second', 99.0);
    $board = new LinuxBoard(new FakeCommandRunner(), oneWire: $bus);

    expect($board->oneWireDevices())->toBe(['28-first', '28-second'])
        ->and($board->ds18b20()->celsius())->toBe(21.0)
        ->and($board->ds18b20('28-second')->celsius())->toBe(99.0);
});

it('throws a helpful error when no sensor is present', function () {
    $board = new LinuxBoard(new FakeCommandRunner(), oneWire: new FakeOneWireReader());

    expect(fn () => $board->ds18b20())->toThrow(RuntimeException::class, 'No DS18B20');
});
