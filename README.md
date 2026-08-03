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

## Status

In development. Ready: event loop, Firmata adapter (digital I/O, analog inputs,
I2C), devices Led / Relay / Buzzer / Button / MotionSensor /
AnalogSensor / Lcd1602 / Mpu6050, FakeBoard for tests without hardware.
Next on the roadmap: Linux/FFI adapter (Raspberry Pi), load cell HX711,
GSM/AT stack, CLI debugging.
