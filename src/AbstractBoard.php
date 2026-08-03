<?php

declare(strict_types=1);

namespace Femus;

use Femus\Contracts\BoardInterface;
use Femus\Contracts\PinMode;
use Femus\Device\Led;
use Femus\Device\Relay;
use Femus\Device\Buzzer;
use Femus\Device\Button;
use Femus\Device\MotionSensor;
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
}
