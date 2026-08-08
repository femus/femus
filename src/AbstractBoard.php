<?php

declare(strict_types=1);

namespace Femus;

use Femus\Contracts\BoardInterface;
use Femus\Contracts\PinMode;
use Femus\Device\AnalogSensor;
use Femus\Device\Buzzer;
use Femus\Device\Button;
use Femus\Device\Lcd1602;
use Femus\Device\Lcd1602Parallel;
use Femus\Device\Led;
use Femus\Device\LoadCell;
use Femus\Device\MotionSensor;
use Femus\Device\Mpu6050;
use Femus\Device\Relay;
use Femus\Device\RgbLed;
use Femus\Runtime\Loop;

abstract class AbstractBoard implements BoardInterface
{
    public function __construct(protected readonly Loop $loop)
    {
    }

    public function loop(): Loop
    {
        return $this->loop;
    }

    public function run(): void
    {
        $this->loop->run();
    }

    public function stop(): void
    {
        $this->loop->stop();
    }

    public function led(int $pin): Led
    {
        return new Led($this->digitalPin($pin, PinMode::Output), $this->loop);
    }

    public function relay(int $pin): Relay
    {
        return new Relay($this->digitalPin($pin, PinMode::Output));
    }

    public function buzzer(int $pin): Buzzer
    {
        return new Buzzer($this->digitalPin($pin, PinMode::Output), $this->loop);
    }

    public function button(int $pin, float $debounceSeconds = 0.02): Button
    {
        return new Button($this->digitalPin($pin, PinMode::InputPullUp), $this->loop, $debounceSeconds);
    }

    public function motionSensor(int $pin): MotionSensor
    {
        return new MotionSensor($this->digitalPin($pin, PinMode::Input), $this->loop);
    }

    public function analogSensor(int $channel, float $threshold = 0.01): AnalogSensor
    {
        return new AnalogSensor($this->analogPin($channel), $this->loop, $threshold);
    }

    public function rgbLed(int $redPin, int $greenPin, int $bluePin): RgbLed
    {
        return new RgbLed($this->pwmPin($redPin), $this->pwmPin($greenPin), $this->pwmPin($bluePin));
    }

    public function lcd1602(int $address = 0x27): Lcd1602
    {
        return new Lcd1602($this->i2c(), $address);
    }

    public function lcd1602Parallel(int $rs, int $en, int $d4, int $d5, int $d6, int $d7): Lcd1602Parallel
    {
        return new Lcd1602Parallel(
            $this->digitalPin($rs, PinMode::Output),
            $this->digitalPin($en, PinMode::Output),
            $this->digitalPin($d4, PinMode::Output),
            $this->digitalPin($d5, PinMode::Output),
            $this->digitalPin($d6, PinMode::Output),
            $this->digitalPin($d7, PinMode::Output),
        );
    }

    public function mpu6050(int $address = 0x68): Mpu6050
    {
        return new Mpu6050($this->i2c(), $address);
    }

    public function loadCell(int $doutPin, int $sckPin, float $thresholdGrams = 1.0): LoadCell
    {
        return new LoadCell($this->scaleInput($doutPin, $sckPin), $this->loop, $thresholdGrams);
    }
}
