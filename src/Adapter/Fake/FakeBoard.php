<?php

declare(strict_types=1);

namespace Femus\Adapter\Fake;

use Femus\AbstractBoard;
use Femus\Contracts\AnalogPin;
use Femus\Contracts\DigitalPin;
use Femus\Contracts\I2cBus;
use Femus\Contracts\PinMode;
use Femus\Contracts\ScaleInput;

final class FakeBoard extends AbstractBoard
{
    /** @var array<int, FakeDigitalPin> */
    private array $pins = [];

    /** @var array<int, FakeAnalogPin> */
    private array $analogPins = [];

    private ?FakeI2cBus $i2c = null;

    /** @var array<string, FakeScaleInput> */
    private array $scaleInputs = [];

    public function digitalPin(int $number, PinMode $mode): DigitalPin
    {
        return $this->pins[$number] ??= new FakeDigitalPin($number, $mode);
    }

    public function analogPin(int $channel): AnalogPin
    {
        return $this->analogPins[$channel] ??= new FakeAnalogPin($channel);
    }

    public function i2c(): I2cBus
    {
        return $this->i2c ??= new FakeI2cBus();
    }

    public function fakeI2c(): FakeI2cBus
    {
        $bus = $this->i2c();
        assert($bus instanceof FakeI2cBus);

        return $bus;
    }

    public function pin(int $number): FakeDigitalPin
    {
        return $this->pins[$number]
            ?? throw new \LogicException("Pin {$number} has not been requested via digitalPin() yet");
    }

    /** Access a test analog pin; throws LogicException if the channel has not been requested. */
    public function fakeAnalogPin(int $channel): FakeAnalogPin
    {
        return $this->analogPins[$channel]
            ?? throw new \LogicException("Analog channel {$channel} has not been requested yet");
    }

    public function simulateInput(int $pin, bool $high): void
    {
        $this->pin($pin)->simulate($high);
    }

    /** Scenario: inject an event after $delaySeconds inside the event loop. */
    public function scheduleInput(float $delaySeconds, int $pin, bool $high): void
    {
        $this->loop->addTimer($delaySeconds, fn () => $this->simulateInput($pin, $high));
    }

    /** Simulates an ADC reading. The channel must be requested via analogPin() beforehand. */
    public function simulateAnalog(int $channel, int $raw): void
    {
        $this->fakeAnalogPin($channel)->simulate($raw);
    }

    public function scheduleAnalog(float $delaySeconds, int $channel, int $raw): void
    {
        $this->loop->addTimer($delaySeconds, fn () => $this->simulateAnalog($channel, $raw));
    }

    public function scaleInput(int $doutPin, int $sckPin): ScaleInput
    {
        return $this->scaleInputs["{$doutPin}:{$sckPin}"] ??= new FakeScaleInput();
    }

    public function fakeScale(int $doutPin, int $sckPin): FakeScaleInput
    {
        return $this->scaleInputs["{$doutPin}:{$sckPin}"]
            ?? throw new \LogicException("Scale input {$doutPin}:{$sckPin} has not been requested yet");
    }

    public function simulateScaleReading(int $doutPin, int $sckPin, int $raw): void
    {
        $this->fakeScale($doutPin, $sckPin)->simulate($raw);
    }

    public function scheduleScaleReading(float $delaySeconds, int $doutPin, int $sckPin, int $raw): void
    {
        $this->loop->addTimer($delaySeconds, fn () => $this->simulateScaleReading($doutPin, $sckPin, $raw));
    }
}
