<?php

declare(strict_types=1);

namespace Femus\Device;

use Femus\Contracts\DigitalPin;

/**
 * A 74HC595 serial-in/parallel-out shift register: 8 outputs driven over
 * three wires (DS data, SHCP clock, STCP latch). Chips can be daisy-chained
 * (Q7' -> DS of the next one) for 16, 24, ... outputs from the same 3 pins.
 *
 * Output n of the chain maps to bit n of the state integer: bit 0 is Q0 of
 * the chip wired to the board, bit 8 is Q0 of the next chip in the chain.
 */
final class ShiftRegister
{
    private int $state = 0;

    public function __construct(
        private readonly DigitalPin $data,
        private readonly DigitalPin $clock,
        private readonly DigitalPin $latch,
        private readonly int $chips = 1,
    ) {
        $this->flush(); // the register powers up with garbage; start all-off
    }

    /** Total number of outputs across the chain (8 per chip). */
    public function outputs(): int
    {
        return $this->chips * 8;
    }

    /** Replace the whole output state (bit n = output n) and flush it to the chip. */
    public function write(int $state): void
    {
        $this->state = $state & ((1 << $this->outputs()) - 1);
        $this->flush();
    }

    /** Switch a single output on or off (0 = Q0 of the first chip). */
    public function set(int $output, bool $on): void
    {
        $this->assertOutput($output);
        $this->write($on
            ? $this->state | (1 << $output)
            : $this->state & ~(1 << $output));
    }

    /** Whether an output is currently on. */
    public function get(int $output): bool
    {
        $this->assertOutput($output);

        return (bool) (($this->state >> $output) & 1);
    }

    /** Switch every output off. */
    public function clear(): void
    {
        $this->write(0);
    }

    /** The full output state as an integer, bit n = output n. */
    public function state(): int
    {
        return $this->state;
    }

    private function assertOutput(int $output): void
    {
        if ($output < 0 || $output >= $this->outputs()) {
            throw new \InvalidArgumentException(
                "Output {$output} is out of range: {$this->chips} chip(s) give outputs 0-" . ($this->outputs() - 1),
            );
        }
    }

    private function flush(): void
    {
        $this->latch->write(false);
        // MSB-first: the first bit in ends up on the highest output after all
        // the clocks, and the leading byte overflows into the last chip of a chain.
        for ($bit = $this->outputs() - 1; $bit >= 0; $bit--) {
            $this->clock->write(false);
            $this->data->write((bool) (($this->state >> $bit) & 1));
            $this->clock->write(true); // rising edge shifts the bit in
        }
        $this->clock->write(false);
        $this->latch->write(true); // rising edge copies the register to the outputs
    }
}
