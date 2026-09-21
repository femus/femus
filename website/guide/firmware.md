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

## femus modem:probe

For a GSM modem there is no firmware to flash — the questions are different: what baud
rate does it speak, is the SIM readable, is it on a network, which commands does this
firmware actually support.

```bash
vendor/bin/femus modem:probe                          # first serial port found
vendor/bin/femus modem:probe /dev/cu.usbserial-130    # or name it
```

```
Port:     /dev/cu.usbserial-130 @ 115200 baud
Modem:    SIMCOM INCORPORATED, A7670G-LABE, V1.11.2, IMEI …0123
SIM:      no SIM inserted
Network:  nothing to register with until a SIM is in
Signal:   21/31 (-71 dBm)
SMS:      text mode, charsets IRA,UCS2,HEX,GSM, storage unreadable (no SIM?)
```

The block is meant to be pasted into `docs/hardware-runs.md`. IMEI, ICCID and the SIM's
own number come out cut to their last digits, because that file is public.

| Line you get | What it means |
|---|---|
| `Quirk: AT+CCID not supported` | this firmware hides the ICCID behind `AT+CICCID` |
| `Quirk: no UCS2 charset` | the module cannot carry Cyrillic or emoji in an SMS |
| `Quirk: SMS storage nearly full` | incoming messages are about to be dropped — `AT+CMGD=1,4` |
| `no such port` | the USB adapter is unplugged; `femus scan` lists what is there |
| `silence at every baud rate` | the link is broken, not mistuned — see below |

Silence deserves the last word. A **wrong** baud rate still returns garbage bytes, so
getting nothing at all means the wiring is at fault, and the command prints the checklist
in the order worth walking: power (a USB-TTL adapter cannot feed a modem's 2 A peaks),
shared GND, the level converter's LV pin, then TX/RX crossed. Every one of those has
cost this project an evening.

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
