<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

final class FirmataParser
{
    private string $buffer = '';

    /** @var list<callable> */
    private array $digitalListeners = [];

    /** @var list<callable> */
    private array $versionListeners = [];

    public function onDigitalMessage(callable $fn): void
    {
        $this->digitalListeners[] = $fn;
    }

    public function onVersion(callable $fn): void
    {
        $this->versionListeners[] = $fn;
    }

    public function push(string $bytes): void
    {
        $this->buffer .= $bytes;

        while ($this->buffer !== '') {
            $first = ord($this->buffer[0]);
            $length = strlen($this->buffer);

            if (($first & 0xF0) === Firmata::DIGITAL_MESSAGE) {
                if ($length < 3) {
                    return; // ждём остаток
                }
                $port = $first & 0x0F;
                $bitmask = ord($this->buffer[1]) | (ord($this->buffer[2]) << 7);
                $this->buffer = substr($this->buffer, 3);
                foreach ($this->digitalListeners as $listener) {
                    $listener($port, $bitmask);
                }
            } elseif ($first === Firmata::REPORT_VERSION) {
                if ($length < 3) {
                    return;
                }
                $major = ord($this->buffer[1]);
                $minor = ord($this->buffer[2]);
                $this->buffer = substr($this->buffer, 3);
                foreach ($this->versionListeners as $listener) {
                    $listener($major, $minor);
                }
            } elseif ($first === Firmata::SYSEX_START) {
                $end = strpos($this->buffer, chr(Firmata::SYSEX_END));
                if ($end === false) {
                    return; // sysex ещё не пришёл целиком
                }
                $this->buffer = substr($this->buffer, $end + 1); // v1: sysex игнорируем
            } else {
                $this->buffer = substr($this->buffer, 1); // неизвестный байт — ресинхронизация
            }
        }
    }
}
