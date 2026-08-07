# Quick Start

Five minutes from an empty file to hardware reacting to your code. This walkthrough
assumes you finished [Installation](/guide/installation) — firmware flashed, board
connected.

## Blink

Create `blink.php`:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Femus\Board;

$board = Board::firmata();   // autodetects the port

$board->led(13)->blink(0.5); // onboard LED, half-second period

$board->run();
```

```bash
php blink.php
```

`Board::firmata()` probes serial ports and connects to the first Firmata board it
finds. Pass the port explicitly (`Board::firmata('/dev/cu.usbserial-1420')`) when you
have several boards connected.

## React to input

Wire a push button between pin **D2** and **GND** (femus enables the internal pull-up
for you), then:

```php
$button = $board->button(2);
$led = $board->led(13);

$button->onPress(fn () => $led->on());
$button->onRelease(fn () => $led->off());

$board->run();
```

Press the button — the LED follows. Debouncing is built in (20 ms by default).

## Read an analog sensor

A potentiometer, light sensor or water-level sensor on **A0**:

```php
$sensor = $board->analogSensor(0);

$sensor->onChange(function (float $value) {
    printf("level: %.0f%%\n", $value * 100);
});

$board->run();
```

Values arrive normalized to `0.0 … 1.0`.

## Add time to the mix

The event loop gives you timers alongside device events:

```php
$loop = $board->loop();

$loop->addPeriodicTimer(1.0, fn () => printf("still alive: %s\n", date('H:i:s')));
$loop->addTimer(60.0, fn () => $board->stop());   // shut down after a minute

$board->run();
```

## Where to go next

- [Board & Ports](/guide/board) — port discovery, multiple boards, one loop.
- [Testing Without Hardware](/guide/testing) — the same code, asserted in CI.
- [Devices](/devices/) — scales, LCDs, gyroscopes, relays and friends.
- [433 MHz Radio](/guide/radio) — packet radio between two boards.
