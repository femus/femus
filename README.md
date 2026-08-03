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
2. `composer require sanchescom/femus`
3. `php examples/blink.php /dev/ttyUSB0`

## Статус

В разработке. Готово: event loop, Firmata-адаптер (цифровой I/O),
Led / Relay / Buzzer / Button / MotionSensor, FakeBoard для тестов без железа.
Дальше по roadmap: Linux/FFI-адаптер (Raspberry Pi), аналоговые входы, I2C,
тензодатчик HX711, GSM/AT-стек, CLI-отладка.
