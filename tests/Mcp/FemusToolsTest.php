<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Cli\Arduino\ArduinoCli;
use Femus\Cli\Command\FlashFirmware;
use Femus\Mcp\Tools\FemusTools;
use Femus\Mcp\ToolRegistry;
use Femus\Runtime\StreamSelectLoop;
use Femus\Tests\Cli\FakeCommandRunner;
use Femus\Transport\SerialPortLocator;

function makeTools(array $ports, ?FakeBoard $board = null, ?FakeCommandRunner $runner = null): ToolRegistry
{
    $locator = new SerialPortLocator(fn (string $pattern): array => $ports);
    $flash = new FlashFirmware(new ArduinoCli($runner ?? new FakeCommandRunner()), $locator, '/project');
    $registry = new ToolRegistry();
    (new FemusTools($locator, $flash, fn (?string $port) => $board ?? new FakeBoard(new StreamSelectLoop())))
        ->registerOn($registry);

    return $registry;
}

it('registers the five hardware tools', function () {
    $names = array_column(makeTools([])->list(), 'name');
    expect($names)->toBe(['scan_ports', 'flash_firmware', 'digital_write', 'digital_read', 'analog_read']);
});

it('scan_ports lists candidates or says none found', function () {
    expect(makeTools(['/dev/ttyUSB0'])->call('scan_ports', []))->toBe('/dev/ttyUSB0')
        ->and(makeTools([])->call('scan_ports', []))->toBe('No boards found.');
});

it('digital_write drives the pin on the board', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $result = makeTools([], $board)->call('digital_write', ['pin' => 13, 'high' => true]);

    expect($result)->toBe('pin D13 set HIGH')
        ->and($board->pin(13)->read())->toBeTrue();
});

it('digital_read reports the pin state', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->digitalPin(2, Femus\Contracts\PinMode::InputPullUp);
    $board->simulateInput(2, false);

    expect(makeTools([], $board)->call('digital_read', ['pin' => 2]))->toBe('pin D2 is LOW');
});

it('analog_read reports the normalized value', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->analogPin(0);
    $board->simulateAnalog(0, 512);

    expect(makeTools([], $board)->call('analog_read', ['channel' => 0]))->toContain('raw 512');
});

it('flash_firmware surfaces failures as tool errors', function () {
    $registry = makeTools([]); // no ports → auto port resolution fails
    expect(fn () => $registry->call('flash_firmware', ['target' => 'femus']))
        ->toThrow(Femus\Mcp\McpToolException::class);
});
