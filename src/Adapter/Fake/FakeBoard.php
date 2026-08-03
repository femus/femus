<?php

declare(strict_types=1);

namespace Femus\Adapter\Fake;

use Femus\AbstractBoard;
use Femus\Contracts\AnalogPin;
use Femus\Contracts\DigitalPin;
use Femus\Contracts\PinMode;

final class FakeBoard extends AbstractBoard
{
    /** @var array<int, FakeDigitalPin> */
    private array $pins = [];

    /** @var array<int, FakeAnalogPin> */
    private array $analogPins = [];

    public function digitalPin(int $number, PinMode $mode): DigitalPin
    {
        return $this->pins[$number] ??= new FakeDigitalPin($number, $mode);
    }

    public function analogPin(int $channel): AnalogPin
    {
        return $this->analogPins[$channel] ??= new FakeAnalogPin($channel);
    }

    public function pin(int $number): FakeDigitalPin
    {
        return $this->pins[$number]
            ?? throw new \LogicException("Пин {$number} ещё не запрошен через digitalPin()");
    }

    /** Доступ к тестовому пину; бросает LogicException, если канал не запрошен. */
    public function fakeAnalogPin(int $channel): FakeAnalogPin
    {
        return $this->analogPins[$channel]
            ?? throw new \LogicException("Аналоговый канал {$channel} ещё не запрошен");
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

    /** Имитация отсчёта АЦП. Канал должен быть заранее запрошен через analogPin(). */
    public function simulateAnalog(int $channel, int $raw): void
    {
        $this->fakeAnalogPin($channel)->simulate($raw);
    }

    public function scheduleAnalog(float $delaySeconds, int $channel, int $raw): void
    {
        $this->loop->addTimer($delaySeconds, fn () => $this->simulateAnalog($channel, $raw));
    }
}
