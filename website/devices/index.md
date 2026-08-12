# Devices

Every driver is created from the board object and follows the same conventions:
events via `on*()` callbacks, blocking `waitFor*()` helpers where they make sense,
and full support in [`FakeBoard`](/guide/testing) for hardware-free tests.

| Driver | Hardware | Highlights |
|---|---|---|
| `led(pin)` | any LED / digital output | `on()`, `off()`, `toggle()`, `blink(period)` |
| `relay(pin)` | relay module | `on()`, `off()` |
| `buzzer(pin)` | active buzzer | `beep(seconds)` |
| `shiftRegister(data, clock, latch, chips?)` | [74HC595](/devices/74hc595) — 8+ outputs from 3 pins | `set(n, on)`, `write(bits)`, `clear()`, daisy-chaining |
| `button(pin)` | [push button](/devices/button) to GND | `onPress()`, `onRelease()`, `waitForPress()` — debounced, pull-up enabled |
| `motionSensor(pin)` | PIR (HC-SR501…) | `onMotion()`, `onIdle()`, `waitForMotion()` |
| `rotaryEncoder(clk, dt, sw?)` | [KY-040 rotary encoder](/devices/rotary-encoder) | `onTurn(±1)`, `position()`, optional push `button()` |
| `analogSensor(ch)` | [pot, LDR, water level…](/devices/analog-sensor) | `onChange()` with normalized 0–1 values |
| `loadCell(dout, sck)` | [HX711 + strain gauges](/devices/hx711) | `tare()`, `calibrate(grams)`, `grams()`, `onChange()` |
| `pantryJar(dout, sck, ...)` | jar/container on a load cell | `contentsGrams()`, `percentFull()`, `servingsLeft()`, `isLow()` — [smart pantry guide](/guide/pantry) |
| `lcd1602()` | [16×2 LCD](/devices/lcd1602) via I2C backpack | `write()`, cursor control |
| `lcd1602Parallel(...)` | [16×2 LCD](/devices/lcd1602) on 6 GPIO | same API, no backpack needed |
| `mpu6050()` | [gyro/accelerometer](/devices/mpu6050) (I2C) | orientation and acceleration readings |
| `ds18b20(id?)` | [1-Wire thermometer](/devices/ds18b20) on a Raspberry Pi | `celsius()`, `fahrenheit()`, CRC-checked reads |
| `radioLink(addr)` | [433 MHz TX+RX pair](/devices/radio-433) | [full guide](/guide/radio) |

New to wiring? Start with [wiring basics](/devices/wiring-basics) and the
[Arduino Nano pinout](/devices/arduino-nano-pinout). Every driver also has a
runnable script in
[`examples/`](https://github.com/femus/femus/tree/main/examples).
