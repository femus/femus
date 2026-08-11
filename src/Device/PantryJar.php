<?php

declare(strict_types=1);

namespace Femus\Device;

/**
 * A pantry jar (or any container) sitting on a load cell.
 *
 * The load cell reports the total weight on the scale; the jar knows how much
 * the empty container weighs, so it can report how much *contents* are left,
 * how full it is, and how many servings remain.
 *
 * Calibrate the underlying {@see LoadCell} first (tare the empty scale, then
 * calibrate against a known weight) — reach it via {@see scale()}.
 */
final class PantryJar
{
    /**
     * @param float $emptyGrams     weight of the empty container (subtracted from every reading)
     * @param float $fullGrams      contents weight when the jar is full (0 = unknown → percentFull() is null)
     * @param float $gramsPerServing weight of one serving (0 = unknown → servingsLeft() is null)
     */
    public function __construct(
        private readonly LoadCell $scale,
        private readonly float $emptyGrams = 0.0,
        private readonly float $fullGrams = 0.0,
        private readonly float $gramsPerServing = 0.0,
    ) {
    }

    /** The underlying load cell, for taring and calibration. */
    public function scale(): LoadCell
    {
        return $this->scale;
    }

    /** Grams of contents left (total on the scale minus the empty container), never negative. */
    public function contentsGrams(): float
    {
        return max(0.0, $this->scale->grams() - $this->emptyGrams);
    }

    /** How full the jar is, 0–100%, or null if the full amount is unknown. */
    public function percentFull(): ?float
    {
        if ($this->fullGrams <= 0.0) {
            return null;
        }

        return min(100.0, max(0.0, $this->contentsGrams() / $this->fullGrams * 100.0));
    }

    /** Whole servings left, or null if the serving size is unknown. */
    public function servingsLeft(): ?int
    {
        if ($this->gramsPerServing <= 0.0) {
            return null;
        }

        return (int) floor($this->contentsGrams() / $this->gramsPerServing);
    }

    /** True when the contents are at or below the given threshold (time to restock). */
    public function isLow(float $thresholdGrams): bool
    {
        return $this->contentsGrams() <= $thresholdGrams;
    }

    /**
     * Register a callback fired whenever the contents change.
     * The callback receives the contents weight in grams (empty container already subtracted).
     *
     * @param callable(float): void $fn
     */
    public function onChange(callable $fn): void
    {
        $this->scale->onChange(function (float $grams) use ($fn): void {
            $fn(max(0.0, $grams - $this->emptyGrams));
        });
    }
}
