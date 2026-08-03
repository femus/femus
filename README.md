# femus

PHP hardware framework: датчики, реле, кнопки и модули — из обычного PHP.
Один код работает через Arduino (Firmata по USB) и Raspberry Pi (в планах).

```php
use Femus\Board;

$board = Board::firmata('/dev/ttyUSB0');

$button = $board->button(2);
$led = $board->led(13);

$button->onPress(fn () => $led->on());
$button->onRelease(fn () => $led->off());

$board->run();
```

## Быстрый старт

1. Прошей Arduino стандартной прошивкой StandardFirmata
   (Arduino IDE → File → Examples → Firmata → StandardFirmata).
2. Пока пакет не опубликован на Packagist — установка из репозитория:
   добавь в composer.json своего проекта:
   `"repositories": [{"type": "vcs", "url": "https://github.com/sanchescom/femus"}]`
   затем: `composer require sanchescom/femus:dev-main`
3. `php examples/blink.php /dev/ttyUSB0`

## Status

In development. Ready: event loop, Firmata adapter (digital I/O, analog inputs,
I2C), devices Led / Relay / Buzzer / Button / MotionSensor /
AnalogSensor / Lcd1602 / Mpu6050, FakeBoard for tests without hardware.
Next on the roadmap: Linux/FFI adapter (Raspberry Pi), load cell HX711,
GSM/AT stack, CLI debugging.
