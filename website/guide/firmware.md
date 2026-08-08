# Firmware & CLI

## femus scan

Before anything else, find your board:

```bash
vendor/bin/femus scan
```

```
Found 1 serial port:
  ✓ /dev/ttyUSB0 — Firmata board — femus-ready
```

It lists every serial port and tells you, in plain language, what each one is:

| Mark | Meaning |
|---|---|
| `✓ … femus-ready` | a Firmata board answered — you're good to go |
| `· … no response` | a port opened but nothing answered — flash it (`femus firmware:flash femus`) |
| `· … in use` | the port is held by another program — close the Arduino IDE / serial monitor |

## The firmware model

You never write or edit Arduino code with femus. Two sketches ship **precompiled**
inside the composer package (`firmware/build/*.ino.hex`):

- **FemusFirmata** — the workhorse: ConfigurableFirmata (digital, analog, I2C) plus
  femus's custom features — HX711 load cells and 433 MHz packet radio. This is what
  your `Board::firmata()` talks to.
- **RadioBleBridge** — a standalone sketch for the radio messenger's phone node:
  bridges BLE (HM-10 module) to 433 MHz radio. Node addresses are configured over
  BLE (`/addr 2`, `/peer 1`, `/show`) and persist in EEPROM — one hex fits everyone.

## femus firmware:flash

```bash
vendor/bin/femus firmware:flash femus          # the Firmata station
vendor/bin/femus firmware:flash radio-bridge   # the BLE bridge node
```

What it does:

1. Checks that [arduino-cli](https://arduino.github.io/arduino-cli/latest/installation/) is installed.
2. Autodetects the board's serial port (or takes `--port=...`).
3. Installs the `arduino:avr` core if missing (one-time; provides the uploader).
4. Uploads the bundled hex as-is — **no compilation, no libraries**.

| Flag | Meaning |
|---|---|
| `--port=/dev/...` | explicit port (default: autodetect) |
| `--fqbn=arduino:avr:nano` | new-bootloader Nano (default: `arduino:avr:nano:cpu=atmega328old`) |
| `--build` | compile from source instead of the bundled hex |

The hex is identical for both Nano bootloaders — `--fqbn` only affects upload speed,
so if flashing fails with `programmer is not responding`, try the other one.

## Building from source

After modifying a sketch (contributors only):

```bash
vendor/bin/femus firmware:flash femus --build     # femus installs the libraries itself
```

To refresh the bundled hexes:

```bash
arduino-cli compile --fqbn arduino:avr:nano firmware/FemusFirmata --output-dir firmware/build
arduino-cli compile --fqbn arduino:avr:nano firmware/RadioBleBridge --output-dir firmware/build
```

CI compiles both sketches on every push, so a broken sketch never lands in `main`.
