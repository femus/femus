<?php

declare(strict_types=1);

namespace Femus\Transport;

/** Тестовый транспорт: written копит исходящее, feed() подаёт входящее. */
final class InMemoryTransport implements Transport
{
    public string $written = '';

    /** @var resource читаемая сторона (отдаётся в event loop) */
    private $local;

    /** @var resource пишущая сторона (feed) */
    private $remote;

    public function __construct()
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false) {
            throw new TransportException('stream_socket_pair failed');
        }
        [$this->local, $this->remote] = $pair;
        stream_set_blocking($this->local, false);
    }

    public function write(string $bytes): void
    {
        $this->written .= $bytes;
    }

    public function feed(string $bytes): void
    {
        fwrite($this->remote, $bytes);
    }

    public function stream()
    {
        return $this->local;
    }

    public function readAvailable(): string
    {
        return (string) stream_get_contents($this->local);
    }

    public function close(): void
    {
        fclose($this->local);
        fclose($this->remote);
    }
}
