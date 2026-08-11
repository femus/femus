# Devices

Every driver is created from the board object and follows the same conventions:
events via `on*()` callbacks, blocking `waitFor*()` helpers where they make sense,
and full support in [`FakeBoard`](/guide/testing) for hardware-free tests.

| Driver | Hardware | Highlights |
|---|---|---|
| `led(pin)` | any LED / digital output | `on()`, `off()`, `toggle()`, `blink(period)` |
| `relay(pin)` | relay module | `on()`, `off()` |
| `buzzer(pin)` | active buzzer | `beep(seconds)` |
| `button(pin)` | push button to GND | `onPress()`, `onRelease()`, `waitForPress()` — debounced, pull-up enabled |
| `motionSensor(pin)` | PIR (HC-SR501…) | `onMotion()`, `onIdle()`, `waitForMotion()` |
| `rotaryEncoder(clk, dt, sw?)` | KY-040 rotary encoder | `onTurn(±1)`, `position()`, optional push `button()` |
| `analogSensor(ch)` | pot, LDR, water level… | `onChange()` with normalized 0–1 values |
| `loadCell(dout, sck)` | HX711 + strain gauges | `tare()`, `calibrate(grams)`, `grams()`, `onChange()` |
| `pantryJar(dout, sck, ...)` | jar/container on a load cell | `contentsGrams()`, `percentFull()`, `servingsLeft()`, `isLow()` — [smart pantry guide](/guide/pantry) |
| `lcd1602()` | 16×2 LCD via I2C backpack | `write()`, cursor control |
| `lcd1602Parallel(...)` | 16×2 LCD on 6 GPIO | same API, no backpack needed |
| `mpu6050()` | gyro/accelerometer (I2C) | orientation and acceleration readings |
| `radioLink(addr)` | 433 MHz TX+RX pair | [full guide](/guide/radio) |

Detailed per-device pages — wiring diagrams, calibration walkthroughs, gotchas — are
being migrated from the maintainers' hardware notes. Until then, the
[`examples/`](https://github.com/femus/femus/tree/main/examples) directory has a
working script for every driver, and
[`docs/devices/`](https://github.com/femus/femus/tree/main/docs/devices) in the
repository holds the raw wiring guides.
