<?php

declare(strict_types=1);

namespace Femus\Device;

use Femus\Contracts\DigitalPin;

/**
 * A KY-040 style incremental rotary encoder (two quadrature pins CLK + DT,
 * plus an optional push switch).
 *
 * Turning the knob produces steps; the direction is decoded from the two pins.
 * We act on the falling edge of CLK and read DT at that moment — one step per
 * detent for a typical KY-040. DT high at that edge counts as clockwise (+1),
 * DT low as counter-clockwise (-1). If your knob counts backwards, swap the
 * CLK and DT pins.
 */
final class RotaryEncoder
{
    /** @var list<callable> */
    private array $turnListeners = [];

    private int $position = 0;

    public function __construct(
        private readonly DigitalPin $clk,
        private readonly DigitalPin $dt,
        private readonly ?Button $button = null,
    ) {
        $clk->onChange(function (bool $high): void {
            if ($high) {
                return; // ignore the rising edge; one count per detent on the fall
            }

            $direction = $this->dt->read() ? 1 : -1;
            $this->position += $direction;
            foreach ($this->turnListeners as $listener) {
                $listener($direction, $this->position);
            }
        });
    }

    /**
     * Fired on every step. The callback receives the direction (+1 clockwise,
     * -1 counter-clockwise) and the running position.
     *
     * @param callable(int, int): void $fn
     */
    public function onTurn(callable $fn): void
    {
        $this->turnListeners[] = $fn;
    }

    /** Net steps since construction (+ clockwise, - counter-clockwise). */
    public function position(): int
    {
        return $this->position;
    }

    /** Reset the running position to zero. */
    public function resetPosition(): void
    {
        $this->position = 0;
    }

    /** The push switch, if the SW pin was wired, for onPress()/onRelease(). */
    public function button(): ?Button
    {
        return $this->button;
    }
}
