<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;
use Femus\Device\ConsumptionEstimator;

// Smart pantry jar: a container on an HX711 load cell reports how much is left,
// how full it is, servings remaining, and — over time — the consumption rate.
//
// HX711: VCC -> 5V, GND -> GND, DT -> D3, SCK -> D2 (FemusFirmata required)
// Usage: php examples/pantry.php [port] [empty-jar-grams] [full-grams] [grams-per-serving]
$board = Board::firmata($argv[1] ?? null);

$emptyGrams = (float) ($argv[2] ?? 180.0);   // an empty glass jar, say
$fullGrams = (float) ($argv[3] ?? 800.0);    // contents weight when brim-full
$perServing = (float) ($argv[4] ?? 15.0);    // one spoon of sugar ~ 15 g

$jar = $board->pantryJar(3, 2, emptyGrams: $emptyGrams, fullGrams: $fullGrams, gramsPerServing: $perServing);
$estimator = new ConsumptionEstimator();

echo "Empty the scale (remove the jar) so we can zero it...\n";
$start = time();
while ($jar->scale()->raw() === null) {
    if (time() - $start > 5) {
        echo "Warning: no HX711 reading after 5s. Check wiring.\n";
        exit(1);
    }
    $board->loop()->tick(0.1);
}
$jar->scale()->tare();

// Calibrate once with a known weight, e.g. a 100 g reference on the bare scale.
// Uncomment for a real setup:
//   echo "Put a 100 g reference weight on the scale, then press Enter...\n"; fgets(STDIN);
//   $board->loop()->tick(0.3); $jar->scale()->calibrate(100.0);

echo "Put the jar back. Reading the pantry...\n";

$jar->onChange(function (float $contents) use ($jar, $estimator) {
    $estimator->record((float) time(), $contents);

    $line = sprintf('%.0f g left', $contents);

    $percent = $jar->percentFull();
    if ($percent !== null) {
        $line .= sprintf(' (%.0f%% full)', $percent);
    }

    $servings = $jar->servingsLeft();
    if ($servings !== null) {
        $line .= sprintf(', ~%d servings', $servings);
    }

    $perDay = $estimator->gramsPerDay();
    if ($perDay !== null) {
        $days = $estimator->daysLeft($contents);
        $line .= sprintf(', using %.0f g/day', $perDay);
        if ($days !== null) {
            $line .= sprintf(' → lasts ~%.1f days', $days);
        }
    }

    if ($jar->isLow(50.0)) {
        $line .= '  ⚠ running low!';
    }

    echo $line . "\n";
});

$board->run();
