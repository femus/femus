<?php

declare(strict_types=1);

namespace Femus\Cli;

use Femus\Adapter\Firmata\BoardException;
use Femus\Adapter\Firmata\FirmataBoard;
use Femus\Board;
use Femus\Cli\Arduino\ArduinoCli;
use Femus\Cli\Command\FlashFirmware;
use Femus\Cli\Command\FlashOptions;
use Femus\Cli\Command\FmRadio;
use Femus\Cli\Command\ProbeModem;
use Femus\Cli\Command\ScanPorts;
use Femus\Cli\Process\SystemCommandRunner;
use Femus\Mcp\McpServer;
use Femus\Mcp\ToolRegistry;
use Femus\Mcp\Tools\FemusTools;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\SerialPort;
use Femus\Transport\SerialPortLocator;
use Femus\Transport\TransportException;

final class Application
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @param list<string> $argv full argv, script name at [0]
     * @param callable(string): void $out
     */
    public function run(array $argv, callable $out): int
    {
        $command = $argv[1] ?? null;

        if ($command === 'firmware:flash') {
            $parsed = FlashOptions::parse(array_slice($argv, 2));
            $flash = new FlashFirmware(
                new ArduinoCli(new SystemCommandRunner()),
                new SerialPortLocator(),
                $this->projectRoot,
            );

            return $flash->run($parsed->target, $parsed->options, $out);
        }

        if ($command === 'scan') {
            $scan = new ScanPorts(new SerialPortLocator(), self::probePort(...));

            return $scan->run($out);
        }

        if ($command === 'modem:probe') {
            return (new ProbeModem(new SerialPortLocator()))->run(self::portArgument(array_slice($argv, 2)), $out);
        }

        if ($command === 'fm:scan' || $command === 'fm:tune') {
            $radio = new FmRadio(static fn (?string $port) => Board::firmata($port)->tea5767());
            if ($command === 'fm:scan') {
                $minLevel = null;
                foreach ($argv as $argument) {
                    if (str_starts_with($argument, '--min-level=')) {
                        $minLevel = (int) substr($argument, 12);
                    }
                }

                return $radio->scan(self::portArgument(array_slice($argv, 2)), $minLevel, $out);
            }

            $mhz = $argv[2] ?? '';
            if (!is_numeric($mhz)) {
                $out('usage: femus fm:tune <MHz> [port]   e.g. femus fm:tune 101.5');

                return 2;
            }

            return $radio->tune(self::portArgument(array_slice($argv, 3)), (float) $mhz, $out);
        }

        if ($command === 'mcp') {
            $locator = new SerialPortLocator();
            $registry = new ToolRegistry();
            $tools = new FemusTools(
                $locator,
                new FlashFirmware(new ArduinoCli(new SystemCommandRunner(echo: false)), $locator, $this->projectRoot),
                static fn (?string $port) => Board::firmata($port),
            );
            $tools->registerOn($registry);
            (new McpServer(STDIN, STDOUT, $registry))->run();

            return 0;
        }

        $out('femus — PHP hardware framework CLI');
        $out('usage: femus scan   (list serial ports and detect Firmata boards)');
        $out('       femus firmware:flash <femus|radio-bridge> [--port=auto] [--fqbn=...] [--build]');
        $out('       femus modem:probe [port]   (identify a GSM modem: baud, SIM, network, quirks)');
        $out('       femus fm:scan [port] [--min-level=N]   (TEA5767: list FM stations, tune to the strongest)');
        $out('       femus fm:tune <MHz> [port]');
        $out('       femus mcp   (MCP server over stdio — hardware tools for AI agents)');

        return $command === null ? 0 : 2;
    }

    /**
     * The port from the command's arguments ("port" or "--port=..."), null to auto-detect.
     *
     * @param list<string> $arguments
     */
    private static function portArgument(array $arguments): ?string
    {
        $port = null;
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--port=')) {
                $port = substr($argument, 7);
            } elseif (!str_starts_with($argument, '-')) {
                $port = $argument;
            }
        }

        return $port;
    }

    /** Probe a port: firmata (responds), silent (opens, no Firmata), busy (cannot open). */
    private static function probePort(string $port): string
    {
        try {
            $serial = new SerialPort($port);
        } catch (TransportException) {
            return 'busy';
        }

        try {
            // Cold-open pulses DTR and resets the board; the bootloader eats ~2s
            // before the sketch answers, so allow generous time here.
            (new FirmataBoard($serial, new StreamSelectLoop(), handshakeTimeout: 4.0))->awaitReady();

            return 'firmata';
        } catch (BoardException) {
            return 'silent';
        } finally {
            $serial->close();
        }
    }
}
