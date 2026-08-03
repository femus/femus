<?php

declare(strict_types=1);

namespace Femus\Transport;

final class SerialPort implements Transport
{
    /** @var resource */
    private $stream;

    public function __construct(string $device, int $baudRate = 57600)
    {
        $flag = PHP_OS_FAMILY === 'Darwin' ? '-f' : '-F';
        $command = sprintf(
            'stty %s %s %d cs8 -cstopb -parenb -echo raw',
            $flag,
            escapeshellarg($device),
            $baudRate,
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new TransportException(
                "Не удалось настроить порт {$device}: " . implode("\n", $output),
            );
        }

        $stream = @fopen($device, 'r+b');
        if ($stream === false) {
            throw new TransportException("Не удалось открыть {$device} (права? устройство подключено?)");
        }
        stream_set_blocking($stream, false);
        $this->stream = $stream;
    }

    public function write(string $bytes): void
    {
        if (@fwrite($this->stream, $bytes) === false) {
            throw new TransportException('Ошибка записи в порт (устройство отключено?)');
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
