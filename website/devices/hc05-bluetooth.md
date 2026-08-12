# HC-05 Bluetooth Module (Wireless Firmata)

Replace the USB cable with an HC-05 (or HC-06) Bluetooth serial module.
femus talks the same [Firmata protocol](/guide/firmware) over the wireless UART — no code changes
are needed beyond specifying the Bluetooth serial port instead of `/dev/ttyUSB0`.

## Wiring to Arduino (Firmata)

| HC-05 pin | Arduino pin | Notes |
|-----------|-------------|-------|
| VCC       | 5V          | Module draws up to 50 mA; use the Arduino 5V pin |
| GND       | GND         | Common ground |
| TXD       | D0 (RX)     | HC-05 TX → Arduino RX, direct connection (3.3V signal is safe on AVR input) |
| RXD       | D1 (TX)     | Arduino TX → HC-05 RX **through a voltage divider** (Arduino TX is 5V; HC-05 RX is 3.3V-tolerant only) |

**Voltage divider on the Arduino TX → HC-05 RXD line:**

```
Arduino D1 (TX, 5V) ──── 1 kΩ ──── HC-05 RXD
                                         │
                                        2 kΩ
                                         │
                                        GND
```

The divider produces ~3.33 V at the HC-05 RXD pin:
`5V × 2kΩ / (1kΩ + 2kΩ) ≈ 3.33V` — within the 3.3V logic level the module expects.

> **Important:** disconnect the D0 and D1 jumper wires while flashing the
> Arduino over USB. D0/D1 are shared with the USB-UART chip; having the HC-05
> wired to those pins during a flash will corrupt the upload and may brick the
> bootloader session.

## Breadboard

```
      Arduino Nano
      ┌─────────┐
   5V │ ●       │
  GND │ ●       │
   D0 │ ●       │  (RX ← HC-05 TXD, direct)
   D1 │ ●       │  (TX → voltage divider → HC-05 RXD)
      └─────────┘
        │   │   │   │
        │   │   │   └── D1 (TX) ──── row 10 col a ── 1 kΩ ── row 10 col e
        │   │   │                                              │
        │   │   │                                         row 12 col e ── 2 kΩ ── GND rail (blue)
        │   │   │                                              │
        │   │   │                                         HC-05 RXD ─────────────────────────────┘
        │   │   │
        │   │   └─────── D0 (RX) ──────────────────────────── HC-05 TXD (direct, no divider)
        │   └─────────── GND rail (blue) ──────────────────── HC-05 GND
        └─────────────── 5V rail (red)  ──────────────────── HC-05 VCC

  Voltage divider detail (rows 10–12, columns a–e):
  row 10, col a ── Nano D1 (TX)
  row 10, col a–e ── 1 kΩ resistor body ── row 10, col e
  row 10, col e  ── jumper down ── row 12, col e
  row 12, col e  ── 2 kΩ resistor body ── row 12, col j (or GND rail)
  row 12, col j  ── jumper to GND rail (blue)
  row 10, col e  ── jumper ── HC-05 RXD pin
```

Resistors: **1 kΩ and 2 kΩ required** for the voltage divider on the
Arduino TX → HC-05 RXD line. The HC-05 TXD → Arduino RX connection is direct
(3.3V output is safely read by the 5V AVR input).

**Common mistakes**

1. Connecting Arduino TX (5V) directly to HC-05 RXD without the divider —
   5V on the RXD pin exceeds the module's 3.3V tolerance and will damage the
   HC-05 over time or immediately.
2. Leaving D0/D1 wired to the HC-05 while uploading a new sketch — the
   module holds the RX line and the upload fails; always unplug those two
   jumpers before hitting upload.
3. Misidentifying the 1 kΩ and 2 kΩ resistors — read the colour bands or use
   a multimeter; swapping them produces ~1.67V at RXD (too low, module may not
   recognise the high level) or ~4.17V (still too high for 3.3V logic).

## One-Time HC-05 Setup

### 1. Pair on macOS

1. Power the HC-05 (it blinks rapidly — not paired).
2. Open **System Settings → Bluetooth**, find "HC-05" (or "HC-06"), click **Connect**.
3. Enter PIN **1234** (try **0000** if rejected).
4. A virtual serial port appears: `/dev/cu.HC-05-XXXX` (or similar suffix).

### 2. Set Baud Rate to 57600 via AT Mode

Firmata runs at **57600 baud**. Factory default is often 9600; you must change
it once.

**Enter AT mode:**

1. Unpower the module.
2. Hold the small button on the HC-05 module.
3. Apply power while holding the button — the LED blinks slowly (≈2 s period).
   This is AT mode. Release the button.

**Send AT commands at 38400 baud** (the default AT-mode baud):

```bash
# macOS: replace cu.usbserial-XXXX with your Arduino's upload port
screen /dev/cu.usbserial-XXXX 38400
```

Inside `screen`, type each command followed by Enter (CR+LF — `screen` sends
CR by default; the HC-05 accepts it):

```
AT                        # should reply OK
AT+UART=57600,0,0         # set baud 57600, 1 stop bit, no parity → reply OK
AT+NAME=HC-05             # optional: rename the device
AT+RESET                  # restart; module exits AT mode, blinks rapidly
```

Exit `screen` with `Ctrl-A` then `\`.

### 3. Run Firmata Wirelessly

```bash
php examples/blink.php /dev/cu.HC-05-DevB
```

Replace `/dev/cu.HC-05-DevB` with the actual port name shown in macOS. No
changes to the PHP code are needed — femus uses the same Firmata protocol
over the Bluetooth UART as over USB. See
[`examples/blink.php`](https://github.com/femus/femus/blob/main/examples/blink.php)
and the [quick start](/guide/quick-start) for what the script does.
