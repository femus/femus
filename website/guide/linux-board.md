# Raspberry Pi GPIO (no Arduino)

A Raspberry Pi has its own GPIO header, so for digital work you don't need an Arduino
at all. `Board::linux()` drives the Pi's pins directly — and the device drivers you
already know work unchanged.

```php
use Femus\Board;

$board = Board::linux();          // the Pi's own GPIO, via pinctrl

$board->led(17)->blink(0.5);      // LED on GPIO17
$board->button(27)->onPress(fn () => echo "pressed!\n");

$board->run();
```

`led()`, `relay()`, `buzzer()`, `button()`, `motionSensor()` and the parallel
`lcd1602Parallel()` all work — they're built on digital pins, and `LinuxBoard`
provides those straight from the Pi.

Pin numbers are **BCM GPIO numbers** (the same ones `pinctrl` and most Pi pinout
diagrams use), not physical header positions.

## What needs an Arduino instead

The Pi's hardware has limits that a microcontroller doesn't:

| Feature | On the Pi? | Why |
|---|---|---|
| Digital in/out | ✅ `Board::linux()` | native GPIO |
| Analog inputs | ❌ | the Pi has **no ADC** — use `Board::firmata()` or an SPI ADC (MCP3008) |
| HX711 scales, 433 MHz radio | ❌ | timing-critical — use `Board::firmata()` |
| I2C / SPI | 🚧 | planned |

Calling `analogPin()`, `radioLink()` or `scaleInput()` on a `LinuxBoard` throws a
clear message pointing you to `Board::firmata()`.

## Requirements

- Raspberry Pi OS with the `pinctrl` utility (preinstalled on current images).
- Your user in the `gpio` group (the default `pi`/first user already is):
  `sudo usermod -aG gpio $USER` then re-login.

## How it works

`LinuxBoard` shells out to `pinctrl`, which writes the GPIO registers directly — so a
pin set HIGH stays HIGH after the command returns. Input pins are polled (there are no
interrupts here), which is fine for buttons and sensors at human speed. For tight
timing or analog, reach for the [Firmata board](/guide/board) — femus is happy to run
both in one script.

::: tip Same code, two backends
Write against the board object and you can move a sketch between a Pi's own pins and an
Arduino by swapping `Board::linux()` for `Board::firmata()`. Nothing else changes.
:::
