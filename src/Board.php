<?php

declare(strict_types=1);

namespace Femus;

use Femus\Adapter\Fake\FakeBoard;
use Femus\Adapter\Firmata\BoardException;
use Femus\Adapter\Firmata\FirmataBoard;
use Femus\Adapter\Linux\LinuxBoard;
use Femus\Runtime\Loop;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\SerialPort;
use Femus\Transport\SerialPortLocator;
use Femus\Transport\TransportException;

final class Board
{
    public static function fake(): FakeBoard
    {
        return new FakeBoard(new StreamSelectLoop());
    }

    /** The host's own GPIO header (Raspberry Pi via pinctrl) — no Arduino needed. */
    public static function linux(?Loop $loop = null): LinuxBoard
    {
        return new LinuxBoard(loop: $loop ?? new StreamSelectLoop());
    }

    /** Arduino with StandardFirmata firmware connected via USB. */
    public static function firmata(?string $device = null, int $baudRate = 57600, ?Loop $loop = null): FirmataBoard
    {
        if ($device !== null) {
            return FirmataBoard::open($device, $baudRate, $loop);
        }

        $locator = new SerialPortLocator();
        $ports = $locator->candidates();

        if ($ports === []) {
            throw new BoardException('No serial ports found. Is the board connected?');
        }

        $sharedLoop = $loop ?? new StreamSelectLoop();
        $probed = [];

        foreach ($ports as $port) {
            try {
                $transport = new SerialPort($port, $baudRate);
            } catch (TransportException) {
                $probed[] = $port;
                continue;
            }

            try {
                $board = new FirmataBoard($transport, $sharedLoop, handshakeTimeout: 5.0);
                $board->awaitReady();

                return $board;
            } catch (TransportException | BoardException) {
                $sharedLoop->removeReadStream($transport->stream());
                $transport->close();
                $probed[] = $port;
            }
        }

        throw new BoardException('No Firmata board found. Probed ports: ' . implode(', ', $probed));
    }

    private function __construct()
    {
    }
}
