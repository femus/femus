# Arduino Nano V3 Pinout Reference

Hold the board with the **mini-USB connector pointing up (away from you)**.
Landmarks: the RST push-button sits mid-board; the "L" LED (the one
[blink.php](https://github.com/femus/femus/blob/main/examples/blink.php)
drives) is near the bottom-right corner next to D13.

```
                    ┌──[ mini-USB ]──┐
        (TX1)  D1 ──┤ 1          30 ├── VIN  (7-12V input)
        (RX0)  D0 ──┤ 2          29 ├── GND
            RESET ──┤ 3          28 ├── RESET
              GND ──┤ 4          27 ├── +5V
   button →   D2 ───┤ 5          26 ├── A7
 HX711 DT →   D3 ───┤ 6          25 ├── A6
              D4 ───┤ 7          24 ├── A5  ← I2C SCL (LCD, GY-521)
              D5 ───┤ 8          23 ├── A4  ← I2C SDA (LCD, GY-521)
              D6 ───┤ 9          22 ├── A3
              D7 ───┤ 10         21 ├── A2
              D8 ───┤ 11         20 ├── A1
              D9 ───┤ 12         19 ├── A0  ← analog sensors (water level…)
             D10 ───┤ 13         18 ├── AREF
             D11 ───┤ 14         17 ├── 3V3
             D12 ───┤ 15         16 ├── D13 ← onboard "L" LED
                    └───[ RST btn ]──┘
```

## Pins used by femus examples

| Pin | Used by | Example |
|-----|---------|---------|
| D2  | Button (internal pull-up) | [button-led.php](https://github.com/femus/femus/blob/main/examples/button-led.php) |
| D2  | HX711 SCK (⚠️ conflicts with the button — not at the same time) | [scale.php](https://github.com/femus/femus/blob/main/examples/scale.php) |
| D3  | HX711 DT | [scale.php](https://github.com/femus/femus/blob/main/examples/scale.php) |
| D13 | Onboard LED | [blink.php](https://github.com/femus/femus/blob/main/examples/blink.php), [button-led.php](https://github.com/femus/femus/blob/main/examples/button-led.php) |
| A0  | Analog sensors (water level, photoresistor) | [water-level.php](https://github.com/femus/femus/blob/main/examples/water-level.php) |
| A4  | I2C SDA (LCD1602, GY-521) | [lcd-clock.php](https://github.com/femus/femus/blob/main/examples/lcd-clock.php), [gyro-dump.php](https://github.com/femus/femus/blob/main/examples/gyro-dump.php) |
| A5  | I2C SCL (LCD1602, GY-521) | [lcd-clock.php](https://github.com/femus/femus/blob/main/examples/lcd-clock.php), [gyro-dump.php](https://github.com/femus/femus/blob/main/examples/gyro-dump.php) |
| D0/D1 | UART — shared with USB; also HC-05 wireless link | [HC-05 Bluetooth](/devices/hc05-bluetooth) |
| 5V / GND | Module power | everything |

Notes:
- D2/GND are adjacent on the left column (pins 4 and 5 from the top) — the
  button needs just those two.
- A4/A5 double as the I2C bus — while an LCD or GY-521 is connected, those
  two analog channels are unavailable.
- D0/D1 are shared with the USB-serial chip: disconnect anything wired to
  them (e.g. HC-05) before [flashing over USB](/guide/firmware).
