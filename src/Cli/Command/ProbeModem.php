<?php

declare(strict_types=1);

namespace Femus\Cli\Command;

use Femus\Gsm\AtChannel;
use Femus\Gsm\ModemProbe;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\SerialPort;
use Femus\Transport\SerialPortLocator;
use Femus\Transport\TransportException;

/**
 * "femus modem:probe" — meet a new GSM module. Finds its baud rate, asks it who it is,
 * and prints a block for docs/hardware-runs.md.
 *
 * Silence at every baud rate is itself the finding: a wrong baud still returns garbage
 * bytes, so nothing at all means the wiring is broken, and the checklist says where.
 */
final class ProbeModem
{
    /** Most modems sit at the first; the rest cover the cheap 2G boards and odd defaults. */
    private const BAUD_RATES = [115200, 9600, 57600, 19200, 38400];

    /** @param null|callable(string, int): ?ModemProbe $connect injected in tests */
    public function __construct(
        private readonly SerialPortLocator $locator,
        private $connect = null,
    ) {
        $this->connect ??= self::openModem(...);
    }

    /** @param callable(string): void $out */
    public function run(?string $port, callable $out): int
    {
        $port ??= $this->locator->candidates()[0] ?? null;
        if ($port === null) {
            $out('No serial ports found. Connect the modem over USB and try again.');

            return 1;
        }

        // a port that is not there at all is an unplugged cable, not a wiring fault
        if (!file_exists($port)) {
            $out("{$port}: no such port. Is the USB adapter plugged in? Run 'femus scan' to list ports.");

            return 1;
        }

        foreach (self::BAUD_RATES as $baud) {
            $probe = ($this->connect)($port, $baud);
            if ($probe === null) {
                continue;
            }

            $out(sprintf('Port:     %s @ %d baud', $port, $baud));
            foreach ($probe->run() as $line) {
                $out($line);
            }
            $out('');
            $out('Paste the block above into docs/hardware-runs.md.');

            return 0;
        }

        $out(sprintf('%s: silence at every baud rate (%s).', $port, implode(', ', self::BAUD_RATES)));
        $out('');
        $out('A wrong baud rate still returns garbage — nothing at all means the link is broken.');
        $out('Check, in this order:');
        $out('  1. Power. A USB-TTL adapter cannot feed a modem (2 A peaks) — use the modem\'s own supply.');
        $out('  2. GND shared between adapter, level converter and modem. Missing GND looks exactly like this.');
        $out('  3. Level converter: measure its LV pin — it must sit at the modem\'s logic voltage, not 0 V.');
        $out('  4. TX and RX crossed: adapter TXD goes to modem RX, modem TX goes to adapter RXD.');

        return 1;
    }

    /** Opens the port at one baud rate and returns a probe if the modem answers "AT". */
    private static function openModem(string $port, int $baud): ?ModemProbe
    {
        try {
            $channel = new AtChannel(new SerialPort($port, $baud), new StreamSelectLoop(), 2.0);
        } catch (TransportException) {
            return null;
        }

        $send = static fn (string $command) => $channel->send($command);

        // ATE0 first: an echoing modem would put the command itself in every reply
        $send('ATE0');

        return $send('AT')->ok ? new ModemProbe($send) : null;
    }
}
