<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

use Femus\AbstractBoard;
use Femus\Contracts\DigitalPin;
use Femus\Contracts\PinMode;
use Femus\Runtime\Loop;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\SerialPort;
use Femus\Transport\Transport;

final class FirmataBoard extends AbstractBoard
{
    /** @var array<int, FirmataPin> */
    private array $pins = [];

    /** @var array<int, int> битмаски выходных портов */
    private array $portState = [];

    private readonly FirmataParser $parser;

    private bool $ready = false;

    public function __construct(
        private readonly Transport $transport,
        Loop $loop,
        private readonly float $handshakeTimeout = 5.0,
    ) {
        parent::__construct($loop);

        $this->parser = new FirmataParser();
        $this->parser->onVersion(function (): void {
            $this->ready = true;
        });
        $this->parser->onDigitalMessage($this->handleDigitalMessage(...));

        $loop->addReadStream(
            $transport->stream(),
            fn () => $this->parser->push($transport->readAvailable()),
        );
    }

    public static function open(string $device, int $baudRate = 57600, ?Loop $loop = null): self
    {
        $board = new self(new SerialPort($device, $baudRate), $loop ?? new StreamSelectLoop());
        $board->awaitReady();

        return $board;
    }

    public function awaitReady(): void
    {
        $deadline = hrtime(true) / 1e9 + $this->handshakeTimeout;
        while (!$this->ready) {
            if (hrtime(true) / 1e9 >= $deadline) {
                throw new BoardException(
                    'Arduino не ответила. Прошита ли StandardFirmata? Верный ли порт?',
                );
            }
            $this->loop->tick(0.1);
        }
    }

    public function digitalPin(int $number, PinMode $mode): DigitalPin
    {
        if (!isset($this->pins[$number])) {
            $firmataMode = match ($mode) {
                PinMode::Output => Firmata::MODE_OUTPUT,
                PinMode::Input => Firmata::MODE_INPUT,
                PinMode::InputPullUp => Firmata::MODE_PULLUP,
            };
            $this->transport->write(FirmataEncoder::setPinMode($number, $firmataMode));
            if ($mode !== PinMode::Output) {
                $this->transport->write(FirmataEncoder::reportDigitalPort(intdiv($number, 8), true));
            }
            $this->pins[$number] = new FirmataPin($number, $mode, $this);
        }

        return $this->pins[$number];
    }

    /** @internal */
    public function writeDigital(int $pin, bool $high): void
    {
        $port = intdiv($pin, 8);
        $bit = 1 << ($pin % 8);
        $mask = $this->portState[$port] ?? 0;
        $mask = $high ? ($mask | $bit) : ($mask & ~$bit);
        $this->portState[$port] = $mask;
        $this->transport->write(FirmataEncoder::digitalWrite($port, $mask));
    }

    private function handleDigitalMessage(int $port, int $bitmask): void
    {
        foreach ($this->pins as $number => $pin) {
            if (intdiv($number, 8) !== $port || $pin->mode === PinMode::Output) {
                continue;
            }
            $pin->updateFromBoard((bool) ($bitmask & (1 << ($number % 8))));
        }
    }
}
