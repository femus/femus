<?php

declare(strict_types=1);

namespace Femus\Cli;

use Femus\Adapter\Firmata\BoardException;
use Femus\Adapter\Firmata\FirmataBoard;
use Femus\Board;
use Femus\Cli\Arduino\ArduinoCli;
use Femus\Cli\Command\FlashFirmware;
use Femus\Cli\Command\FlashOptions;
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
        $out('       femus mcp   (MCP server over stdio — hardware tools for AI agents)');

        return $command === null ? 0 : 2;
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
            (new FirmataBoard($serial, new StreamSelectLoop(), handshakeTimeout: 2.0))->awaitReady();

            return 'firmata';
        } catch (BoardException) {
            return 'silent';
        } finally {
            $serial->close();
        }
    }
}
