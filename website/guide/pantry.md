# Smart Pantry — how much is left in the jar?

Put a jar of sugar, coffee, dog food — anything — on a load cell and femus tells
you how much is **left**, how **full** it is, how many **servings** remain, and
(over time) how fast you're going through it: *"350 g of sugar, ~50 g/day, lasts
about a week."*

It's two small pieces on top of the [`loadCell`](/devices) driver:

- **`PantryJar`** — knows the empty container's weight, so a raw scale reading
  becomes *contents*. Reports grams left, percent full, servings, and a low-stock flag.
- **`ConsumptionEstimator`** — pure math over `(time, grams)` samples. Fits a line
  through them to get a consumption rate and a days-left estimate.

## Wiring

Same as any HX711 load cell — see the [load cell](/devices) notes. `DT → D3`,
`SCK → D2`, powered from 5 V, with [FemusFirmata](/guide/firmware) on the Arduino.

## Reading the jar

```php
use Femus\Board;

$board = Board::firmata();

$jar = $board->pantryJar(
    doutPin: 3,
    sckPin: 2,
    emptyGrams: 180.0,      // weight of the empty jar
    fullGrams: 800.0,       // contents weight when brim-full (enables percentFull)
    gramsPerServing: 15.0,  // one spoonful (enables servingsLeft)
);

// Zero and calibrate the underlying load cell once:
$jar->scale()->tare();            // with the jar removed
// ...put a known 100 g weight on, then:
$jar->scale()->calibrate(100.0);

$jar->onChange(function (float $contents) use ($jar) {
    printf(
        "%.0f g left — %.0f%% full — ~%d servings%s\n",
        $contents,
        $jar->percentFull(),
        $jar->servingsLeft(),
        $jar->isLow(50.0) ? '  ⚠ restock!' : '',
    );
});

$board->run();
```

`fullGrams` and `gramsPerServing` are optional: leave them at `0` and
`percentFull()` / `servingsLeft()` return `null` instead of guessing.

## Estimating consumption

`ConsumptionEstimator` holds no clock — you feed it timestamped readings, which
keeps it trivially testable. Record a sample whenever the weight changes:

```php
use Femus\Device\ConsumptionEstimator;

$estimator = new ConsumptionEstimator();

$jar->onChange(function (float $contents) use ($estimator) {
    $estimator->record((float) time(), $contents);

    $perDay = $estimator->gramsPerDay();          // null until it has a downhill trend
    if ($perDay !== null) {
        printf("Using %.0f g/day → lasts ~%.1f more days\n",
            $perDay, $estimator->daysLeft($contents));
    }
});
```

It uses a least-squares fit, so a few noisy readings won't throw off the trend.
When the weight is flat or goes **up** (you refilled the jar), `gramsPerDay()` and
`daysLeft()` return `null` — there's nothing to project.

The full showcase is
[`examples/pantry.php`](https://github.com/femus/femus/blob/main/examples/pantry.php).

## Testing without hardware

Both pieces run entirely on [`FakeBoard`](/guide/testing) — feed raw readings with
`simulateScaleReading()` and assert on the results, no HX711 needed:

```php
$board = new FakeBoard(new StreamSelectLoop());
$jar = $board->pantryJar(3, 2, emptyGrams: 200.0, fullGrams: 500.0);

$board->simulateScaleReading(3, 2, 0);   $jar->scale()->tare();
$board->simulateScaleReading(3, 2, 100); $jar->scale()->calibrate(100.0);

$board->simulateScaleReading(3, 2, 500); // 200 g jar + 300 g contents
expect($jar->contentsGrams())->toBe(300.0);
expect($jar->percentFull())->toBe(60.0);
```
