<?php

declare(strict_types=1);

namespace Femus\Transport;

final class SerialPort implements Transport
{
    /** @var resource */
    private $stream;

    public function __construct(string $device, int $baudRate = 57600)
    {
        // Open first, configure second: on macOS termios settings applied by
        // stty are discarded once the device is fully closed, so the port must
        // be held open while stty runs for the settings to stick.
        $stream = @fopen($device, 'r+b');
        if ($stream === false) {
            throw new TransportException("Failed to open {$device} (permissions? device connected?)");
        }
        stream_set_blocking($stream, false);
        $this->stream = $stream;

        $flag = PHP_OS_FAMILY === 'Darwin' ? '-f' : '-F';
        $command = sprintf(
            'stty %s %s %d cs8 -cstopb -parenb -echo raw',
            $flag,
            escapeshellarg($device),
            $baudRate,
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            fclose($stream);
            throw new TransportException(
                "Failed to configure port {$device}: " . implode("\n", $output),
            );
        }
    }

    public function write(string $bytes): void
    {
        if (@fwrite($this->stream, $bytes) === false) {
            throw new TransportException('Write error (device disconnected?)');
        }
    }

    public function stream()
    {
        return $this->stream;
    }

    public function readAvailable(): string
    {
        return (string) stream_get_contents($this->stream);
    }

    public function close(): void
    {
        fclose($this->stream);
    }
}
