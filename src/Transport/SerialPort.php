<?php

declare(strict_types=1);

namespace Femus\Transport;

use Sanchescom\Serial\SerialException;
use Sanchescom\Serial\SerialPort as Port;

/**
 * Serial transport: a thin adapter over sanchescom/php-serial, which grew out of this class.
 * The package keeps what femus relies on: fopen before stty (macOS drops termios settings
 * on close), a non-blocking stream, readAvailable() that never waits, cs8 -cstopb -parenb raw.
 */
final class SerialPort implements Transport
{
    private Port $port;

    public function __construct(string $device, int $baudRate = 57600)
    {
        try {
            $this->port = new Port($device, $baudRate);
        } catch (SerialException $e) {
            throw new TransportException($e->getMessage(), previous: $e);
        }
    }

    public function write(string $bytes): void
    {
        try {
            $this->port->write($bytes);
        } catch (SerialException $e) {
            throw new TransportException($e->getMessage(), previous: $e);
        }
    }

    public function stream()
    {
        return $this->port->stream();
    }

    public function readAvailable(): string
    {
        return $this->port->readAvailable();
    }

    public function close(): void
    {
        $this->port->close();
    }
}
