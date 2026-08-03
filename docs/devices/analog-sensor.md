# Analog Sensors (water level, photoresistor, joystick)

## Wiring to Arduino (Firmata)

| Sensor pin   | Arduino |
|--------------|---------|
| S (signal)   | A0      |
| + (power)    | 5V      |
| − (ground)   | GND     |

Channels A0–A5 correspond to analogSensor(0)…analogSensor(5).

## Code

```php
$sensor = $board->analogSensor(0);
echo $sensor->read(); // 0.0–1.0
$sensor->onChange(fn (float $v) => print("{$v}\n"));
```

## Verification

`php examples/water-level.php <port>` — dip the sensor in a glass of water, the percentage rises.
