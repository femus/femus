# femus

PHP hardware framework: sensors, relays, buttons and modules — from plain PHP.
One codebase runs on Arduino (Firmata over USB) and Raspberry Pi (planned).

```php
use Femus\Board;

$board = Board::firmata('/dev/ttyUSB0');

$button = $board->button(2);
$led = $board->led(13);

$button->onPress(fn () => $led->on());
$button->onRelease(fn () => $led->off());

$board->run();
```

## Quick start

1. Flash your Arduino with the StandardFirmata firmware
   (Arduino IDE → File → Examples → Firmata → StandardFirmata).
2. The package is not yet published on Packagist — install from the repository:
   add to your project's composer.json:
   `"repositories": [{"type": "vcs", "url": "https://github.com/sanchescom/femus"}]`
   then: `composer require sanchescom/femus:dev-main`
3. `php examples/blink.php /dev/ttyUSB0`

### Прошивка платы одной командой

```bash
composer require sanchescom/femus
vendor/bin/femus firmware:flash radio-bridge   # или femus
```

Ставится только `arduino-cli`; femus подтянет ядро и библиотеки и зальёт готовый
скетч. Ни IDE, ни ручного управления библиотеками, ни C++.

## Port discovery

`Board::firmata()` with no arguments (or `null`) automatically probes available serial ports
and connects to the first Firmata board it finds:

```php
$board = Board::firmata(); // auto-discovery
```

To see available ports manually:
- macOS: `ls /dev/cu.*`
- Linux: `ls /dev/ttyUSB*` or `ls /dev/ttyACM*`

## Multiple boards

Run multiple boards from a single PHP process by sharing a `StreamSelectLoop`:

```php
use Femus\Board;
use Femus\Runtime\StreamSelectLoop;

$loop = new StreamSelectLoop();
$board1 = Board::firmata('/dev/cu.usbserial-A', loop: $loop);
$board2 = Board::firmata('/dev/cu.usbserial-B', loop: $loop);

// ... use board1, board2 ...

$loop->run(); // drives both boards
```

See `examples/two-boards.php` for a complete example (button on one board drives LED on another).

## Status

In development. Ready: event loop, Firmata adapter (digital I/O, analog inputs,
I2C), devices Led / Relay / Buzzer / Button / MotionSensor /
AnalogSensor / Lcd1602 / Mpu6050 / LoadCell (HX711), FakeBoard for tests without hardware,
GSM/AT stack (Femus\Gsm: AtChannel, GsmModem — SMS send/receive),
433 MHz radio (RadioLink — addressed packets, CRC),
phone-side BLE terminal ([ios/FemusRadioTerminal](ios/FemusRadioTerminal/README.md) — SwiftUI iOS app for the radio messenger).

**Note**: LoadCell (HX711) and radio require the FemusFirmata sketch. See [firmware/README.md](firmware/README.md) for flashing.

Next on the roadmap: Linux/FFI adapter (Raspberry Pi), CLI debugging.
