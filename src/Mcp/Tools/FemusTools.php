<?php

declare(strict_types=1);

namespace Femus\Mcp\Tools;

use Femus\Cli\Command\FlashFirmware;
use Femus\Contracts\BoardInterface;
use Femus\Contracts\PinMode;
use Femus\Mcp\McpToolException;
use Femus\Mcp\ToolRegistry;
use Femus\Transport\SerialPortLocator;

/** Registers the femus hardware tools on an MCP tool registry. */
final class FemusTools
{
    /** @param callable(?string): BoardInterface $boardFactory */
    public function __construct(
        private readonly SerialPortLocator $locator,
        private readonly FlashFirmware $flash,
        private $boardFactory,
    ) {
    }

    public function registerOn(ToolRegistry $registry): void
    {
        $port = ['type' => 'string', 'description' => 'Serial port (omit to autodetect)'];

        $registry->register(
            'scan_ports',
            'List serial ports that look like connected boards.',
            ['type' => 'object', 'properties' => new \stdClass()],
            function (): string {
                $candidates = $this->locator->candidates();

                return $candidates === [] ? 'No boards found.' : implode("\n", $candidates);
            },
        );

        $registry->register(
            'flash_firmware',
            'Flash bundled femus firmware to a board. Targets: "femus" (Firmata station), "radio-bridge" (BLE-radio bridge).',
            [
                'type' => 'object',
                'properties' => [
                    'target' => ['type' => 'string', 'enum' => ['femus', 'radio-bridge']],
                    'port' => $port,
                    'fqbn' => ['type' => 'string', 'description' => 'Board FQBN, default arduino:avr:nano:cpu=atmega328old'],
                ],
                'required' => ['target'],
            ],
            function (array $args): string {
                $options = [];
                foreach (['port', 'fqbn'] as $key) {
                    if (isset($args[$key]) && is_string($args[$key])) {
                        $options[$key] = $args[$key];
                    }
                }
                $lines = [];
                $code = $this->flash->run(
                    is_string($args['target'] ?? null) ? $args['target'] : null,
                    $options,
                    function (string $line) use (&$lines): void {
                        $lines[] = $line;
                    },
                );
                if ($code !== 0) {
                    throw new McpToolException("Flash failed (exit {$code}):\n" . implode("\n", $lines));
                }

                return implode("\n", $lines);
            },
        );

        $registry->register(
            'digital_write',
            'Set a digital pin HIGH or LOW (drives LEDs, relays, buzzers).',
            [
                'type' => 'object',
                'properties' => [
                    'pin' => ['type' => 'integer'],
                    'high' => ['type' => 'boolean'],
                    'port' => $port,
                ],
                'required' => ['pin', 'high'],
            ],
            function (array $args): string {
                $board = $this->board($args);
                $pin = (int) $args['pin'];
                $board->digitalPin($pin, PinMode::Output)->write((bool) $args['high']);
                $this->spin($board, 0.05);

                return sprintf('pin D%d set %s', $pin, $args['high'] ? 'HIGH' : 'LOW');
            },
        );

        $registry->register(
            'digital_read',
            'Read a digital pin (with the internal pull-up enabled; buttons to GND read LOW when pressed).',
            [
                'type' => 'object',
                'properties' => [
                    'pin' => ['type' => 'integer'],
                    'port' => $port,
                ],
                'required' => ['pin'],
            ],
            function (array $args): string {
                $board = $this->board($args);
                $pin = (int) $args['pin'];
                $digital = $board->digitalPin($pin, PinMode::InputPullUp);
                $this->spin($board, 0.3);

                return sprintf('pin D%d is %s', $pin, $digital->read() ? 'HIGH' : 'LOW');
            },
        );

        $registry->register(
            'analog_read',
            'Read an analog channel (A0…A7). Returns the normalized 0–1 value.',
            [
                'type' => 'object',
                'properties' => [
                    'channel' => ['type' => 'integer'],
                    'port' => $port,
                ],
                'required' => ['channel'],
            ],
            function (array $args): string {
                $board = $this->board($args);
                $channel = (int) $args['channel'];
                $analog = $board->analogPin($channel);
                $got = false;
                $analog->onChange(function () use (&$got, $board): void {
                    $got = true;
                    $board->stop();
                });
                $this->spin($board, 1.5);
                if (!$got && $analog->readRaw() === 0) {
                    // A genuinely grounded input and "no data yet" both read 0 — report the raw state honestly.
                    return sprintf('channel A%d reads 0.00 (raw 0) — check wiring if a signal was expected', $channel);
                }

                return sprintf('channel A%d reads %.2f (raw %d)', $channel, $analog->read(), $analog->readRaw());
            },
        );
    }

    /** @param array<string, mixed> $args */
    private function board(array $args): BoardInterface
    {
        $port = isset($args['port']) && is_string($args['port']) && $args['port'] !== '' ? $args['port'] : null;

        try {
            return ($this->boardFactory)($port);
        } catch (\Throwable $e) {
            throw new McpToolException('Cannot connect to the board: ' . $e->getMessage());
        }
    }

    /** Run the board loop briefly so reports can arrive. */
    private function spin(BoardInterface $board, float $seconds): void
    {
        $board->loop()->addTimer($seconds, fn () => $board->stop());
        $board->run();
    }
}
