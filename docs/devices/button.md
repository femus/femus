# Button (KY-004 module or any tactile button)

## Wiring to Arduino (Firmata adapter)

| Button pin  | Arduino |
|-------------|---------|
| S (signal)  | D2      |
| − (ground)  | GND     |

The middle (+) pin of the KY-004 module is left unconnected: femus enables the
internal pull-up (INPUT_PULLUP), so pressing the button pulls the signal line to
ground (active-low).

## Code

```php
$button = $board->button(2);
$button->onPress(fn () => print("pressed\n"));
$board->run();
```

## Verification

`php examples/button-led.php /dev/ttyUSB0` — hold the button and the on-board
LED (pin 13) lights up.
