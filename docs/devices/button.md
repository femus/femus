# Button (KY-004 module or any tactile button)

## Wiring to Arduino (Firmata adapter)

| Button pin  | Arduino |
|-------------|---------|
| S (signal)  | D2      |
| − (ground)  | GND     |

The middle (+) pin of the KY-004 module is left unconnected: femus enables the
internal pull-up (INPUT_PULLUP), so pressing the button pulls the signal line to
ground (active-low).

## Breadboard

```
      Arduino Nano
      ┌─────────┐
   5V │ ●       │
  GND │ ●       │
   D2 │ ●       │
      └─────────┘
        │   │
        │   └──────────────────── GND rail (blue) ────────────── button leg B ┐
        └──────────────────────── D2  ────────────────────────── button leg A ┘
                                                                  (same row)

  Power rails:
  + (red)  ── not used for this module
  − (blue) ── Arduino GND ── button leg B
```

Resistors: none needed — femus enables INPUT_PULLUP on the signal pin, so the
Arduino's internal ~50 kΩ pull-up holds the line high when the button is open.

**Common mistakes**

1. Connecting the "+" middle pin of the KY-004 module to 5V — the internal
   pull-up is then fighting the external voltage; leave "+" unconnected.
2. Plugging both button legs into the same breadboard row — the button is
   always "pressed"; make sure the legs straddle the center gap.
3. Using D0 or D1 for the button — those pins are shared with the USB-UART
   and will interfere with Firmata communication; use D2 or higher.

## Code

```php
$button = $board->button(2);
$button->onPress(fn () => print("pressed\n"));
$board->run();
```

## Verification

`php examples/button-led.php /dev/ttyUSB0` — hold the button and the on-board
LED (pin 13) lights up.
