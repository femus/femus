# Devices

Every driver is created from the board object and follows the same conventions:
events via `on*()` callbacks, blocking `waitFor*()` helpers where they make sense,
and full support in [`FakeBoard`](../../website/guide/testing.md) for hardware-free tests.

| Driver | Hardware | Highlights |
|---|---|---|
| `led(pin)` | any LED / digital output | `on()`, `off()`, `toggle()`, `blink(period)` |
| `relay(pin)` | relay module | `on()`, `off()` |
| `buzzer(pin)` | active buzzer | `beep(seconds)` |
| `shiftRegister(data, clock, latch, chips?)` | [74HC595](74hc595.md) — 8+ outputs from 3 pins | `set(n, on)`, `write(bits)`, `clear()`, daisy-chaining |
| `button(pin)` | [push button](button.md) to GND | `onPress()`, `onRelease()`, `waitForPress()` — debounced, pull-up enabled |
| `motionSensor(pin)` | PIR (HC-SR501…) | `onMotion()`, `onIdle()`, `waitForMotion()` |
| `rotaryEncoder(clk, dt, sw?)` | [KY-040 rotary encoder](rotary-encoder.md) | `onTurn(±1)`, `position()`, optional push `button()` |
| `analogSensor(ch)` | [pot, LDR, water level…](analog-sensor.md) | `onChange()` with normalized 0–1 values |
| `loadCell(dout, sck)` | [HX711 + strain gauges](hx711.md) | `tare()`, `calibrate(grams)`, `grams()`, `onChange()` |
| `pantryJar(dout, sck, ...)` | jar/container on a load cell | `contentsGrams()`, `percentFull()`, `servingsLeft()`, `isLow()` — [smart pantry guide](../../website/guide/pantry.md) |
| `lcd1602()` | [16×2 LCD](lcd1602.md) via I2C backpack | `write()`, cursor control |
| `lcd1602Parallel(...)` | [16×2 LCD](lcd1602.md) on 6 GPIO | same API, no backpack needed |
| `mpu6050()` | [gyro/accelerometer](mpu6050.md) (I2C) | orientation and acceleration readings |
| `tea5767()` | [FM receiver](tea5767.md) (I2C) | `tune(mhz)`, `status()` level/stereo, `scan()` the band, `mute()`, `mono()` |
| `ds18b20(id?)` | [1-Wire thermometer](ds18b20.md) on a Raspberry Pi | `celsius()`, `fahrenheit()`, CRC-checked reads |
| `radioLink(addr)` | [433 MHz TX+RX pair](radio-433.md) | [full guide](../../website/guide/radio.md) |

New to wiring? Start with [wiring basics](wiring-basics.md) and the
[Arduino Nano pinout](arduino-nano-pinout.md). Every driver also has a
runnable script in
[`examples/`](https://github.com/femus/femus/tree/main/examples).
