# Analog Sensors (water level, photoresistor, joystick)

## Wiring to Arduino (Firmata)

| Sensor pin   | Arduino |
|--------------|---------|
| S (signal)   | A0      |
| + (power)    | 5V      |
| − (ground)   | GND     |

Channels A0–A5 correspond to `analogSensor(0)`…`analogSensor(5)`.

## Breadboard

```
      Arduino Nano
      ┌─────────┐
   5V │ ●       │
  GND │ ●       │
   A0 │ ●       │
      └─────────┘
        │   │   │
        │   │   └── A0 ────────────── sensor S (signal) pin
        │   └─────── GND rail (blue) ── sensor − (ground) pin
        └─────────── 5V rail (red)  ── sensor + (power)  pin

  All three sensor wires land in three separate breadboard rows;
  the power rails distribute 5V and GND across the board.
```

Resistors: none needed — analog sensor modules (water level, photoresistor,
joystick) include onboard resistors; the Arduino ADC input has no pull-down
requirement for these module types.

**Common mistakes**

1. Swapping S and + — the signal line goes to 5V permanently, ADC reads ~1023
   regardless of the physical input; check the label printed on the module.
2. Using 3.3V instead of 5V — most KY-style modules are rated for 5V;
   the reading will be clipped and noisy on 3.3V.
3. Leaving the ground wire disconnected — the floating reference causes
   random, drifting ADC values even when the sensor is untouched.

## Code

```php
$sensor = $board->analogSensor(0);
echo $sensor->read(); // 0.0–1.0
$sensor->onChange(fn (float $v) => print("{$v}\n"));
```

## Verification

`php examples/water-level.php <port>` — dip the sensor in a glass of water and
the percentage rises. See
[water-level.php](https://github.com/femus/femus/blob/main/examples/water-level.php).
