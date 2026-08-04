<?php

declare(strict_types=1);

namespace Femus\Device;

use Femus\Contracts\ScaleInput;
use Femus\Runtime\Loop;

final class LoadCell
{
    private int $offset = 0;

    private float $scalePerGram = 1.0;

    private float $lastReported = 0.0;

    /** @var list<callable> */
    private array $listeners = [];

    public function __construct(
        private readonly ScaleInput $input,
        private readonly Loop $loop,
        private readonly float $thresholdGrams = 1.0,
    ) {
        $input->onRawReading(function (int $raw): void {
            $grams = ($raw - $this->offset) / $this->scalePerGram;
            if (abs($grams - $this->lastReported) < $this->thresholdGrams) {
                return;
            }
            $this->lastReported = $grams;
            foreach ($this->listeners as $listener) {
                $listener($grams);
            }
        });
    }

    public function raw(): ?int
    {
        return $this->input->lastRaw();
    }

    public function tare(): void
    {
        $this->offset = $this->input->lastRaw()
            ?? throw new \LogicException('No reading received yet — is the scale attached?');
    }

    public function calibrate(float $knownGrams): void
    {
        if ($knownGrams <= 0.0) {
            throw new \InvalidArgumentException('Calibration weight must be positive');
        }
        $raw = $this->input->lastRaw()
            ?? throw new \LogicException('No reading received yet — is the scale attached?');
        $scale = ($raw - $this->offset) / $knownGrams;
        if ($scale === 0.0) {
            throw new \LogicException('Calibration weight produced no signal change — check wiring');
        }
        $this->scalePerGram = $scale;
    }

    public function grams(): float
    {
        $raw = $this->input->lastRaw()
            ?? throw new \LogicException('No reading received yet — is the scale attached?');

        return ($raw - $this->offset) / $this->scalePerGram;
    }

    /** @param callable(float): void $fn receives weight in grams */
    public function onChange(callable $fn): void
    {
        $this->listeners[] = $fn;
    }

    public function waitForWeightAbove(float $grams, ?float $timeoutSeconds = null): bool
    {
        $deadline = $timeoutSeconds === null ? null : hrtime(true) / 1e9 + $timeoutSeconds;
        while (true) {
            if ($this->input->lastRaw() !== null && $this->grams() >= $grams) {
                return true;
            }
            if ($deadline !== null && hrtime(true) / 1e9 >= $deadline) {
                return false;
            }
            $this->loop->tick(0.05);
        }
    }
}
