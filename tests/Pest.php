<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// pest()->extend(Tests\TestCase::class)->in('Feature');

/** The 5 status bytes the chip returns while sitting on $mhz. */
function teaStatus(float $mhz, int $level, bool $stereo = false): string
{
    $pll = (int) round(4 * ($mhz * 1_000_000 + 225_000) / 32_768);

    return pack('C5', 0x80 | ($pll >> 8), $pll & 0xFF, ($stereo ? 0x80 : 0) | 0x37, $level << 4, 0);
}
