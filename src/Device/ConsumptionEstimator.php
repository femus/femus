<?php

declare(strict_types=1);

namespace Femus\Device;

/**
 * Estimates how fast a container's contents are being consumed, and how long
 * they will last, from a series of (time, grams) samples.
 *
 * Pure math — it holds no clock. Feed it timestamped weight readings (seconds
 * and grams) and it fits a straight line through them (least squares) to get a
 * consumption rate. This is the "…350 g of sugar, ~50 g/day, lasts a week" bit.
 */
final class ConsumptionEstimator
{
    private const SECONDS_PER_DAY = 86400.0;

    /** @var list<array{t: float, g: float}> */
    private array $samples = [];

    /** Record a weight reading: seconds since some fixed epoch, grams of contents. */
    public function record(float $timeSeconds, float $grams): void
    {
        $this->samples[] = ['t' => $timeSeconds, 'g' => $grams];
    }

    /** @return list<array{t: float, g: float}> */
    public function samples(): array
    {
        return $this->samples;
    }

    /**
     * Grams consumed per day (positive when the contents are shrinking).
     * Null if there are fewer than two distinct-time samples, or if the contents
     * are steady or growing (a refill) — nothing meaningful to project.
     */
    public function gramsPerDay(): ?float
    {
        $slope = $this->slopePerSecond();
        if ($slope === null || $slope >= 0.0) {
            return null; // steady or refilling → not consuming
        }

        return -$slope * self::SECONDS_PER_DAY;
    }

    /**
     * Estimated days until the current contents run out, or null if we cannot
     * project (too few samples, or not currently being consumed).
     */
    public function daysLeft(float $currentGrams): ?float
    {
        $perDay = $this->gramsPerDay();
        if ($perDay === null || $perDay <= 0.0) {
            return null;
        }

        return max(0.0, $currentGrams) / $perDay;
    }

    /** Least-squares slope of grams over time (grams per second), or null if undefined. */
    private function slopePerSecond(): ?float
    {
        $n = count($this->samples);
        if ($n < 2) {
            return null;
        }

        $meanT = array_sum(array_column($this->samples, 't')) / $n;
        $meanG = array_sum(array_column($this->samples, 'g')) / $n;

        $covariance = 0.0;
        $variance = 0.0;
        foreach ($this->samples as $sample) {
            $dt = $sample['t'] - $meanT;
            $covariance += $dt * ($sample['g'] - $meanG);
            $variance += $dt * $dt;
        }

        if ($variance === 0.0) {
            return null; // all samples at the same time — no slope
        }

        return $covariance / $variance;
    }
}
