<?php

declare(strict_types=1);

namespace Femus\Cli\Command;

use Femus\Adapter\Firmata\BoardException;
use Femus\Contracts\I2cException;
use Femus\Device\Tea5767;
use Femus\Transport\TransportException;

/**
 * "femus fm:scan" and "femus fm:tune" — a TEA5767 on a Firmata board.
 *
 * The scan ends tuned to the strongest station, so plugging in headphones is the check.
 * The chip keeps its frequency after the command exits, as long as the board stays powered.
 */
final class FmRadio
{
    /**
     * @param callable(?string): Tea5767 $connect
     * @param float $settle seconds per scan step; raise it if levels look flat
     */
    public function __construct(private $connect, private readonly float $settle = 0.05)
    {
    }

    /** @param callable(string): void $out */
    public function scan(?string $port, ?int $minLevel, callable $out): int
    {
        return $this->withRadio($port, $out, function (Tea5767 $radio) use ($minLevel, $out): int {
            $out(sprintf('Scanning %.1f–%.1f MHz…', Tea5767::MIN_MHZ, Tea5767::MAX_MHZ));
            $points = $radio->scan(settle: $this->settle);
            $minLevel ??= Tea5767::noiseFloor($points) + 3;
            $stations = Tea5767::stations($points, $minLevel);
            $out(sprintf('Noise floor %d/15, counting stations from %d.', Tea5767::noiseFloor($points), $minLevel));

            if ($stations === []) {
                $out("No station at level {$minLevel} or above.");
                $out('Check the antenna (a ~75 cm wire in the ANT jack), or lower the bar with --min-level=N.');

                return 1;
            }

            $out('');
            foreach ($stations as $station) {
                $out(sprintf(
                    '%6.1f MHz  %s %2d  %s',
                    $station['frequency'],
                    // sprintf pads bytes, and █ is three of them
                    str_repeat('█', $station['level']) . str_repeat(' ', 15 - $station['level']),
                    $station['level'],
                    $station['stereo'] ? 'stereo' : 'mono',
                ));
            }

            usort($stations, fn (array $a, array $b) => $b['level'] <=> $a['level']);
            $best = $stations[0]['frequency'];
            $radio->tune($best);

            $out('');
            $out(sprintf('%d stations. Tuned to %.1f MHz, the strongest — put on headphones.', count($stations), $best));
            $out('Another one: femus fm:tune <MHz>');

            return 0;
        });
    }

    /** @param callable(string): void $out */
    public function tune(?string $port, float $mhz, callable $out): int
    {
        return $this->withRadio($port, $out, function (Tea5767 $radio) use ($mhz, $out): int {
            $radio->tune($mhz);
            usleep(100_000);
            $radio->status();               // right after power-up the first reading is junk
            $status = $radio->status();
            $out(sprintf(
                'Tuned to %.1f MHz: level %d/15, %s.',
                $status['frequency'],
                $status['level'],
                $status['stereo'] ? 'stereo' : 'mono',
            ));

            return 0;
        });
    }

    /**
     * @param callable(string): void $out
     * @param callable(Tea5767): int $use
     */
    private function withRadio(?string $port, callable $out, callable $use): int
    {
        try {
            return $use(($this->connect)($port));
        } catch (BoardException | TransportException $e) {
            $out($e->getMessage());
            $out("Is the board flashed with FemusFirmata? Run 'femus scan' to check.");
        } catch (I2cException) {
            $out('The board answers, but the TEA5767 does not (I2C address 0x60).');
            $out('Check, in this order:');
            $out('  1. SDA to A4, SCL (marked SLC on the module) to A5 — swapping them is the usual fault.');
            $out('  2. Module +5V and GND on the same rails as the Nano\'s 5V and GND.');
        } catch (\InvalidArgumentException $e) {
            $out($e->getMessage());
        }

        return 1;
    }
}
