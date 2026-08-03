<?php

declare(strict_types=1);

namespace Femus\Adapter\Fake;

use Femus\AbstractBoard;
use Femus\Contracts\DigitalPin;
use Femus\Contracts\PinMode;

final class FakeBoard extends AbstractBoard
{
    /** @var array<int, FakeDigitalPin> */
    private array $pins = [];

    public function digitalPin(int $number, PinMode $mode): DigitalPin
    {
        return $this->pins[$number] ??= new FakeDigitalPin($number, $mode);
    }

    public function pin(int $number): FakeDigitalPin
    {
        return $this->pins[$number]
            ?? throw new \LogicException("Пин {$number} ещё не запрошен через digitalPin()");
    }

    public function simulateInput(int $pin, bool $high): void
    {
        $this->pin($pin)->simulate($high);
    }

    /** Сценарий: инжект события через $delaySeconds внутри event loop. */
    public function scheduleInput(float $delaySeconds, int $pin, bool $high): void
    {
        $this->loop->addTimer($delaySeconds, fn () => $this->simulateInput($pin, $high));
    }
}
