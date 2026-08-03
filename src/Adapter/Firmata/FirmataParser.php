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

    /** @var list<callable> */
    private array $analogListeners = [];

    /** @var list<callable> */
    private array $sysexListeners = [];

    public function onDigitalMessage(callable $fn): void
    {
        $this->digitalListeners[] = $fn;
    }

    public function onVersion(callable $fn): void
    {
        $this->versionListeners[] = $fn;
    }

    public function onAnalogMessage(callable $fn): void
    {
        $this->analogListeners[] = $fn;
    }

    public function onSysex(callable $fn): void
    {
        $this->sysexListeners[] = $fn;
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
            } elseif (($first & 0xF0) === Firmata::ANALOG_MESSAGE) {
                if ($length < 3) {
                    return;
                }
                $channel = $first & 0x0F;
                $value = ord($this->buffer[1]) | (ord($this->buffer[2]) << 7);
                $this->buffer = substr($this->buffer, 3);
                foreach ($this->analogListeners as $listener) {
                    $listener($channel, $value);
                }
            } elseif ($first === Firmata::SYSEX_START) {
                $end = strpos($this->buffer, chr(Firmata::SYSEX_END));
                if ($end === false) {
                    return; // sysex ещё не пришёл целиком
                }
                $payload = substr($this->buffer, 1, $end - 1);
                $this->buffer = substr($this->buffer, $end + 1);
                foreach ($this->sysexListeners as $listener) {
                    $listener($payload);
                }
            } else {
                $this->buffer = substr($this->buffer, 1); // неизвестный байт — ресинхронизация
            }
        }
    }
}
