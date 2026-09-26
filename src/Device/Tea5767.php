<?php

declare(strict_types=1);

namespace Femus\Device;

use Femus\Contracts\I2cBus;

/**
 * TEA5767 FM receiver (the blue module with headphone and antenna jacks).
 *
 * The chip has no registers: every write is the full 5-byte state, every read returns
 * 5 status bytes. Any byte written, even a register address, retunes it — so reads go
 * through I2cBus::read(), never readRegister(). The driver keeps that state, so mute(),
 * mono() and softMute() rewrite it at the current frequency.
 *
 * There is no volume control on this chip: it outputs a fixed level.
 */
final class Tea5767
{
    public const MIN_MHZ = 87.5;
    public const MAX_MHZ = 108.0;

    /** High-side injection: the PLL sits 225 kHz above the station. */
    private const IF_HZ = 225_000;
    private const XTAL_HZ = 32_768;

    private ?float $frequency = null;
    private bool $muted = false;
    private bool $mono = false;
    private bool $softMute = false;

    /**
     * @param bool $deEmphasis75us true in the Americas, false (50 µs) in Europe and most of the world
     */
    public function __construct(
        private readonly I2cBus $bus,
        private readonly bool $deEmphasis75us = true,
        private readonly int $address = 0x60,
    ) {
    }

    /**
     * @param ?bool $mute a one-off override for this write (a sweep tunes muted); null keeps mute()
     */
    public function tune(float $mhz, ?bool $mute = null): void
    {
        if ($mhz < self::MIN_MHZ || $mhz > self::MAX_MHZ) {
            throw new \InvalidArgumentException(
                sprintf('%.1f MHz is outside the FM band (%.1f–%.1f).', $mhz, self::MIN_MHZ, self::MAX_MHZ),
            );
        }

        $this->frequency = $mhz;
        $this->write($mute ?? $this->muted);
    }

    /** Silences the output; the chip stays tuned and keeps reporting the signal. */
    public function mute(bool $on = true): void
    {
        $this->muted = $on;
        $this->rewrite();
    }

    /** Forces mono: less hiss on a weak station, at the cost of the stereo image. */
    public function mono(bool $on = true): void
    {
        $this->mono = $on;
        $this->rewrite();
    }

    /** Lets the chip turn the volume down by itself while the signal is weak and noisy. */
    public function softMute(bool $on = true): void
    {
        $this->softMute = $on;
        $this->rewrite();
    }

    public function isMuted(): bool
    {
        return $this->muted;
    }

    /** Settings changed before the first tune() apply when it comes. */
    private function rewrite(): void
    {
        if ($this->frequency !== null) {
            $this->write($this->muted);
        }
    }

    private function write(bool $mute): void
    {
        $pll = (int) round(4 * ((float) $this->frequency * 1_000_000 + self::IF_HZ) / self::XTAL_HZ);

        $this->bus->write($this->address, pack(
            'C5',
            ($mute ? 0x80 : 0) | (($pll >> 8) & 0x3F),
            $pll & 0xFF,
            0x10 | ($this->mono ? 0x08 : 0),                // HLSI: high-side injection; MS: force mono
            0x12 | ($this->softMute ? 0x08 : 0),            // XTAL 32.768 kHz, stereo noise cancelling; SMUTE
            $this->deEmphasis75us ? 0x40 : 0x00,
        ));
    }

    /**
     * @return array{frequency: float, level: int, stereo: bool} level is the chip's 0–15 ADC
     */
    public function status(): array
    {
        $raw = $this->bus->read($this->address, 5);
        $pll = ((ord($raw[0]) & 0x3F) << 8) | ord($raw[1]);

        return [
            'frequency' => round(($pll * self::XTAL_HZ / 4 - self::IF_HZ) / 1_000_000, 1),
            'level' => ord($raw[3]) >> 4,
            'stereo' => (ord($raw[2]) & 0x80) !== 0,
        ];
    }

    /**
     * Steps across the band and reads the signal at every point. Muted while it runs.
     *
     * @param float $settle seconds to wait after tuning before the level is valid
     * @return list<array{frequency: float, level: int, stereo: bool}>
     */
    public function scan(
        float $from = self::MIN_MHZ,
        float $to = self::MAX_MHZ,
        float $step = 0.1,
        float $settle = 0.05,
    ): array {
        // the first reading after power-up or a long jump is junk — take it and drop it
        $this->tune($from, mute: true);
        usleep((int) ($settle * 1_000_000));
        $this->status();

        $points = [];
        $steps = (int) round(($to - $from) / $step);
        for ($i = 0; $i <= $steps; $i++) {
            $this->tune(round($from + $i * $step, 1), mute: true);
            usleep((int) ($settle * 1_000_000));
            $points[] = $this->status();
        }

        return $points;
    }

    /**
     * Picks stations out of a scan: a station bleeds into its neighbours, so only the
     * peak of each hump counts.
     *
     * Empty air still reads 6–9 on the chip's meter, and how high depends on the antenna,
     * so by default the bar is the band's own noise floor (the median level) plus 3.
     *
     * @param list<array{frequency: float, level: int, stereo: bool}> $points
     * @return list<array{frequency: float, level: int, stereo: bool}>
     */
    public static function stations(array $points, ?int $minLevel = null): array
    {
        $minLevel ??= self::noiseFloor($points) + 3;
        $stations = [];
        foreach ($points as $i => $point) {
            $left = $points[$i - 1]['level'] ?? -1;
            $right = $points[$i + 1]['level'] ?? -1;
            // strict on the left, loose on the right: a flat top counts once, at its first point
            if ($point['level'] >= $minLevel && $point['level'] > $left && $point['level'] >= $right) {
                $stations[] = $point;
            }
        }

        return $stations;
    }

    /** @param list<array{level: int}> $points */
    public static function noiseFloor(array $points): int
    {
        $levels = array_column($points, 'level');
        sort($levels);

        return $levels[intdiv(count($levels), 2)] ?? 0;
    }
}
