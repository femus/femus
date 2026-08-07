# Introduction

femus is a hardware framework for PHP. It lets you drive physical devices — buttons,
sensors, relays, scales, LCDs, 433 MHz radio modules, GSM modems — from ordinary PHP
code running on your laptop or a Raspberry Pi, with an Arduino acting as the hands.

```php
use Femus\Board;

$board = Board::firmata();          // finds your Arduino automatically

$button = $board->button(2);
$led = $board->led(13);

$button->onPress(fn () => $led->on());
$button->onRelease(fn () => $led->off());

$board->run();
```

## How it works

Your PHP process talks to an Arduino over USB serial using the
[Firmata protocol](https://github.com/firmata/protocol), extended with custom features
for the hardware that plain Firmata can't handle (HX711 load cells, 433 MHz packet radio).
The Arduino runs a single prebuilt firmware that ships inside the composer package —
you flash it once with `vendor/bin/femus firmware:flash` and never touch C++ again.

On top of that transport, femus gives you:

- **A `Board` object** — the single entry point: `$board->led(13)`, `$board->button(2)`,
  `$board->radioLink(1)`, `$board->loadCell(3, 2)`…
- **Typed device drivers** — each device is a small PHP class with an honest API:
  `LoadCell::onWeight()`, `Lcd1602::write()`, `RadioLink::send()`.
- **An event loop** — timers, periodic tasks, stream watching and device events in one
  `select()`-based loop. No extensions, no ReactPHP dependency.
- **A fake adapter** — `FakeBoard` implements the same interfaces with simulation
  helpers, so your hardware logic is unit-testable without hardware.

## What femus is not

- Not a firmware framework — you don't write or modify Arduino sketches.
- Not an IoT cloud — no accounts, no MQTT broker required, everything is local.
- Not production-certified industrial tooling. It is a framework for makers,
  prototypes, home automation and teaching — with real tests behind it.

## Requirements

- PHP 8.2+ (no extensions beyond the standard build)
- [arduino-cli](https://arduino.github.io/arduino-cli/latest/installation/) for flashing
- An Arduino Nano/Uno (ATmega328P) connected over USB

Ready? Head to [Installation](/guide/installation).
