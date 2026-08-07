# femus

[![CI](https://github.com/femus/femus/actions/workflows/ci.yml/badge.svg)](https://github.com/femus/femus/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb3.svg)](composer.json)

**Hardware for PHP developers.** Buttons, sensors, relays, scales, LCDs, 433 MHz radio,
GSM — driven from plain PHP, with the event loop, device drivers and firmware included.
No C++, no Arduino IDE, no firmware hacking: `composer require`, flash with one command, write PHP.

```php
use Femus\Board;

$board = Board::firmata();          // finds your Arduino automatically

$button = $board->button(2);
$led = $board->led(13);

$button->onPress(fn () => $led->on());
$button->onRelease(fn () => $led->off());

$board->run();
```

## Why femus

- **Plain PHP, real hardware.** One `Board` object, typed device drivers, an event loop
  with timers and streams — the language you already know, talking to physical pins.
- **Batteries included.** The Arduino firmware ships precompiled inside the package.
  `vendor/bin/femus firmware:flash` finds the board and uploads it — the only tool you
  need installed is [arduino-cli](https://arduino.github.io/arduino-cli/latest/installation/).
- **Testable without hardware.** Every driver runs against `FakeBoard` — unit-test your
  hardware logic in CI, no soldering required.
- **Not just blinking LEDs.** Addressed 433 MHz packet radio, load cells, GSM/SMS,
  I2C, LCDs — enough to build a real device, not a demo.

## Quick start

```bash
composer require femus/femus            # not on Packagist yet — see note below
vendor/bin/femus firmware:flash femus   # flashes the bundled firmware to your Arduino
php vendor/femus/femus/examples/blink.php
```

> **Until the Packagist release**, install from the repository — add to your `composer.json`:
> `"repositories": [{"type": "vcs", "url": "https://github.com/femus/femus"}]`,
> then `composer require femus/femus:dev-main`.

The flash command installs the `arduino:avr` core if needed, autodetects the serial port
(`--port=/dev/...` to override) and uploads the prebuilt hex as-is. Pass `--build` to
compile from source instead, `--fqbn` for non-default boards.

## What's in the box

### Devices

| Driver | Hardware | Example |
|---|---|---|
| `Led`, `Relay`, `Buzzer` | any digital output | `examples/blink.php` |
| `Button`, `MotionSensor` | digital inputs with events | `examples/button-led.php` |
| `AnalogSensor` | potentiometers, water level, LDR… | `examples/water-level.php` |
| `LoadCell` | HX711 + strain gauge scales | `examples/scale.php` |
| `Lcd1602` / `Lcd1602Parallel` | 16×2 LCD over I2C or 6 GPIO | `examples/lcd-clock.php` |
| `Mpu6050` | gyroscope/accelerometer (I2C) | `examples/gyro-dump.php` |

### 433 MHz packet radio

Addressed packets with CRC between two boards (RadioHead ASK under the hood),
exposed as a clean PHP API:

```php
$radio = $board->radioLink(address: 1);

$radio->onMessage(function ($message) {
    echo "[node {$message->from}] {$message->message}\n";
});

$radio->send(2, 'hello over the air');
$board->run();
```

`examples/radio-chat.php` is a working terminal chat between two machines — and the
foundation of the **radio messenger**: iPhone ⇄ Mac text chat over 433 MHz with no
internet and no cellular, using the bundled BLE bridge firmware and the
[SwiftUI terminal app](ios/FemusRadioTerminal/README.md) included in this repo.

### GSM / SMS

`Femus\Gsm` speaks AT over any serial transport — send an SMS from PHP through
a SIM800L module: `examples/sms-send.php`.

### Testing without hardware

```php
use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

$board = new FakeBoard(new StreamSelectLoop());
$led = $board->led(13);
$board->button(2)->onPress(fn () => $led->on());

$board->simulateInput(2, false);              // "press" the button from code
expect($board->pin(13)->read())->toBeTrue();  // the LED turned on
```

Simulate analog readings, scale weights, I2C replies and incoming radio messages the same
way. The whole femus test suite (150+ Pest tests) runs like this in CI — so can yours.

## Multiple boards

Share one loop between boards — a button on one drives an LED on another
(`examples/two-boards.php`):

```php
$loop = new StreamSelectLoop();
$board1 = Board::firmata('/dev/cu.usbserial-A', loop: $loop);
$board2 = Board::firmata('/dev/cu.usbserial-B', loop: $loop);
$loop->run();
```

## Firmware

Two sketches ship precompiled in `firmware/build/`:

- **FemusFirmata** — ConfigurableFirmata + custom features (HX711 scales, 433 MHz radio)
  for the PHP-driven board.
- **RadioBleBridge** — standalone phone node for the radio messenger: BLE (HM-10) ⇄ radio,
  addresses configured from the phone and stored in EEPROM.

See [firmware/README.md](firmware/README.md) for wiring and details.

## Status & roadmap

Under active development, pre-1.0. Working today: everything above, verified on real
hardware (Arduino Nano). On the roadmap: Raspberry Pi adapter (FFI GPIO), `femus scan`,
1-Wire (DS18B20), GPS (NMEA), ESP8266 as a WiFi node — see
[docs](docs/) for device guides and hardware notes.

## License

[MIT](LICENSE).
