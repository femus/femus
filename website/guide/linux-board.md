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
| DS18B20 temperature (1-Wire) | ✅ `ds18b20()` | via the kernel w1 driver (see below) |
| I2C / SPI | 🚧 | planned |

Calling `analogPin()`, `radioLink()` or `scaleInput()` on a `LinuxBoard` throws a
clear message pointing you to `Board::firmata()`.

## Requirements

- Raspberry Pi OS with the `pinctrl` utility (preinstalled on current images).
- Your user in the `gpio` group (the default `pi`/first user already is):
  `sudo usermod -aG gpio $USER` then re-login.

## DS18B20 temperature sensor (1-Wire)

The Pi speaks 1-Wire through the kernel, so a DS18B20 needs no Arduino. Enable it
once — add `dtoverlay=w1-gpio` to `/boot/firmware/config.txt` and reboot — then wire
`DATA → GPIO4`, `VCC → 3V3`, `GND → GND`, with a 4.7 kΩ pull-up between DATA and VCC.

```php
$board = Board::linux();

foreach ($board->oneWireDevices() as $id) {
    echo $id . "\n";                     // e.g. 28-0000075c2d3f
}

$thermometer = $board->ds18b20();        // first sensor found, or pass an id
echo $thermometer->celsius() . " °C\n";  // null on a bad-CRC read
echo $thermometer->fahrenheit() . " °F\n";
```

Full script: [`examples/pi-temperature.php`](https://github.com/femus/femus/blob/main/examples/pi-temperature.php).
The sensor reads through an injectable `OneWireReader`, so it's fully testable with
`FakeOneWireReader` — no Pi required.

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
