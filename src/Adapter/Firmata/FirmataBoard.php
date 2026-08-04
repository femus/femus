<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

use Femus\AbstractBoard;
use Femus\Contracts\AnalogPin;
use Femus\Contracts\DigitalPin;
use Femus\Contracts\I2cBus;
use Femus\Contracts\PinMode;
use Femus\Contracts\ScaleInput;
use Femus\Runtime\Loop;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\SerialPort;
use Femus\Transport\Transport;

final class FirmataBoard extends AbstractBoard
{
    /** @var array<int, FirmataPin> */
    private array $pins = [];

    /** @var array<int, int> output port bitmasks */
    private array $portState = [];

    /** @var array<int, FirmataAnalogPin> */
    private array $analogPins = [];

    private readonly FirmataParser $parser;

    private ?FirmataI2cBus $i2c = null;

    /** @var array<string, FirmataScaleInput> */
    private array $scaleInputs = [];

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
        $this->parser->onAnalogMessage($this->handleAnalogMessage(...));

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
            $remaining = $deadline - hrtime(true) / 1e9;
            if ($remaining <= 0) {
                throw new BoardException(
                    'Arduino did not respond. Is StandardFirmata flashed? Is the port correct?',
                );
            }
            $this->loop->tick(min(0.1, $remaining));
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

    public function i2c(): I2cBus
    {
        return $this->i2c ??= new FirmataI2cBus($this->transport, $this->parser, $this->loop);
    }

    public function analogPin(int $channel): AnalogPin
    {
        if (!isset($this->analogPins[$channel])) {
            $this->transport->write(FirmataEncoder::reportAnalogChannel($channel, true));
            $this->analogPins[$channel] = new FirmataAnalogPin($channel);
        }

        return $this->analogPins[$channel];
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

    private function handleAnalogMessage(int $channel, int $value): void
    {
        if (isset($this->analogPins[$channel])) {
            $this->analogPins[$channel]->updateFromBoard($value);
        }
    }

    public function scaleInput(int $doutPin, int $sckPin): ScaleInput
    {
        return $this->scaleInputs["{$doutPin}:{$sckPin}"]
            ??= new FirmataScaleInput($this->transport, $this->parser, $doutPin, $sckPin);
    }
}
