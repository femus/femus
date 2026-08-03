<?php

declare(strict_types=1);

namespace Femus\Contracts;

use Femus\Runtime\Loop;

interface BoardInterface
{
    public function digitalPin(int $number, PinMode $mode): DigitalPin;

    public function analogPin(int $channel): AnalogPin;

    public function loop(): Loop;

    public function run(): void;

    public function stop(): void;
}
