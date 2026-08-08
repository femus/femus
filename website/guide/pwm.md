# PWM — brightness & RGB

PWM (pulse-width modulation) fakes an analog output by switching a pin on and off
fast. femus exposes it for dimming LEDs and driving RGB LEDs.

::: warning Live-tested: pending
The PWM firmware compiles and the PHP layer is unit-tested, but this feature hasn't
yet been confirmed on physical hardware. It will be verified on an RGB LED soon.
:::

## A single PWM pin

```php
$led = $board->pwmPin(6);      // pin 6 (a PWM-capable pin)

$led->write(128);              // ~50% duty, raw 0–255
$led->writeFraction(0.25);     // 25% brightness
```

On an Arduino Nano/Uno the PWM-capable pins are **3, 5, 6, 9, 10, 11** (marked `~`).

## RGB LED

```php
$rgb = $board->rgbLed(redPin: 9, greenPin: 10, bluePin: 11);

$rgb->color(255, 100, 0);      // orange, channels 0–255
$rgb->hex('#00ff88');          // or a hex string
$rgb->off();
```

See `examples/rgb-fade.php` for a color-wheel fade.

## No servo (on the radio firmware)

The AVR `Servo` library and the 433 MHz radio (RadioHead) both need Timer1 on an
ATmega328, so they can't run together. femus keeps radio in the default firmware and
leaves servo out. If you need servos and don't use radio, a servo-enabled build can be
added later.

## Raspberry Pi

`Board::linux()` doesn't do PWM yet (the Pi has only two hardware PWM channels);
`pwmPin()` there throws a clear message. Use `Board::firmata()` for PWM today.
