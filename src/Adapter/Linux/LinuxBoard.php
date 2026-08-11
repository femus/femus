<?php

declare(strict_types=1);

namespace Femus\Adapter\Linux;

use Femus\AbstractBoard;
use Femus\Cli\Process\CommandRunner;
use Femus\Cli\Process\SystemCommandRunner;
use Femus\Contracts\AnalogPin;
use Femus\Contracts\DigitalPin;
use Femus\Contracts\I2cBus;
use Femus\Contracts\OneWireReader;
use Femus\Contracts\PinMode;
use Femus\Contracts\PwmPin;
use Femus\Contracts\RadioLink;
use Femus\Contracts\ScaleInput;
use Femus\Device\Ds18b20;
use Femus\Runtime\Loop;
use Femus\Runtime\StreamSelectLoop;

/**
 * Drives a Raspberry Pi's own GPIO header directly — no Arduino required.
 * Digital I/O goes through `pinctrl`; led()/button()/relay()/buzzer() work as usual.
 *
 * The Pi has no ADC, so analog inputs are unavailable; radio and HX711 need the
 * Firmata board. Use Board::firmata() for those.
 */
final class LinuxBoard extends AbstractBoard
{
    /** @var array<int, LinuxDigitalPin> */
    private array $pins = [];

    public function __construct(
        private readonly CommandRunner $runner = new SystemCommandRunner(echo: false),
        ?Loop $loop = null,
        private readonly OneWireReader $oneWire = new SysfsOneWireReader(),
    ) {
        parent::__construct($loop ?? new StreamSelectLoop());
    }

    /**
     * A DS18B20 temperature sensor on the 1-Wire bus (needs dtoverlay=w1-gpio,
     * sensor data on GPIO4 by default). Pass a device id, or omit it to use the
     * first sensor found on the bus.
     */
    public function ds18b20(?string $deviceId = null): Ds18b20
    {
        $deviceId ??= $this->oneWireDevices()[0]
            ?? throw new \RuntimeException('No DS18B20 found on the 1-Wire bus. Is dtoverlay=w1-gpio enabled?');

        return new Ds18b20($this->oneWire, $deviceId);
    }

    /** @return list<string> ids of the DS18B20 sensors present on the 1-Wire bus */
    public function oneWireDevices(): array
    {
        return $this->oneWire->devices();
    }

    public function digitalPin(int $number, PinMode $mode): DigitalPin
    {
        return $this->pins[$number] ??= new LinuxDigitalPin($this->runner, $number, $mode, $this->loop);
    }

    public function pwmPin(int $pin): PwmPin
    {
        throw new \LogicException('PWM on LinuxBoard is not implemented yet — use Board::firmata() for PWM.');
    }

    public function analogPin(int $channel): AnalogPin
    {
        throw new \LogicException(
            'The Raspberry Pi has no ADC. Read analog sensors through an Arduino '
            . '(Board::firmata()) or an SPI ADC like the MCP3008.',
        );
    }

    public function i2c(): I2cBus
    {
        throw new \LogicException('I2C on LinuxBoard is not implemented yet.');
    }

    public function scaleInput(int $doutPin, int $sckPin): ScaleInput
    {
        throw new \LogicException('HX711 needs the Firmata board — use Board::firmata().');
    }

    public function radioLink(int $address, int $rxPin = 11, int $txPin = 12): RadioLink
    {
        throw new \LogicException('433 MHz radio needs the Firmata board — use Board::firmata().');
    }
}
