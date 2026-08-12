# RadioBleBridge (BLE + 433 MHz Radio Node)

Phone node (Node B) of the femus radio messenger. Bridges iPhone Bluetooth (HM-10 BLE module)
to a 433 MHz ASK radio (FS1000A TX + MX-RM-5V RX). A standalone sketch with no Firmata — each
line received from BLE is sent via radio, and each radio message is echoed to BLE.

---

## Wiring to Arduino Nano

### HM-10 (DSD TECH, Bluetooth Low Energy)

**Use the HM-10 (BLE) — NOT HC-05.** iPhone connects only over Bluetooth LE; HC-05
(Bluetooth Classic) is invisible to iOS. If you have both DSD TECH modules (identical
clear cases), pick the one labelled **HM-10** (chip CC2541).

The DSD TECH HM-10 backplane is labelled `Power 3.6–6V` / `LEVEL:3.3V`: it has an
onboard regulator, so **VCC takes 5 V directly**, while the logic pins are 3.3 V.

| HM-10 pin | Arduino Nano | Notes |
|-----------|--------------|-------|
| VCC | 5V | onboard regulator (Power 3.6–6V) |
| GND | GND | common ground |
| TXD | → D7 (direct) | 3.3 V output — the AVR input reads it fine |
| RXD | ← level converter LV1 ← D8 | 3.3 V logic, must be shifted down |
| STATE / BRK(EN) | — | not used |

Only the board→module direction needs level shifting (D8 → RXD, 5 V down to 3.3 V).
The module's TXD outputs 3.3 V, which is above the AVR's logic-high threshold, so
**TXD → D7 runs direct** — verified on the bench (node B bring-up). Two options for
the RXD line — **this build uses Option A**.

#### Option A (recommended): level converter + AMS1117-3.3

A BSS138 level converter shifts the D8→RXD line and protects the HM-10 input. It
needs a 3.3 V reference on its LV side, supplied by an **AMS1117-3.3** regulator
(VIN from the 5 V rail). The HM-10 itself is still powered from 5 V — the AMS1117's 3.3 V
is used only as the converter's LV reference.

```
AMS1117-3.3:  VIN ← 5V    GND ← GND    OUT → 3.3 V rail (LV reference only)

Level converter:
  HV ← 5V          LV ← 3.3 V (AMS1117 OUT)      both GND ← GND
  HV1 ← Nano D8    LV1 → HM-10 RXD

HM-10 TXD → Nano D7 direct — no channel needed (3.3 V → 5 V input is fine)
```

Match HV1↔LV1 and HV2↔LV2 by the channel numbers printed on the converter.

#### Option B (simplest, no extra modules): resistor divider

No level converter or AMS1117 — drop only the D8→RXD line with a 1k/2k divider; D7←TXD
runs direct (3.3 V is enough for the AVR input). HM-10 VCC still takes 5 V.

```
Arduino D8 (5V) ── 1 kΩ ──┬── HM-10 RXD
                          2 kΩ
                           │
                          GND
Arduino D7 ─────────────────── HM-10 TXD (direct)
```

The divider gives ~3.33 V: `5V × 2kΩ / (1kΩ + 2kΩ) ≈ 3.33V`. Fine for 9600 baud UART.

---

> **Different wiring?** The radio pins are `#define RADIO_RX_PIN` / `RADIO_TX_PIN` at
> the top of `RadioBleBridge.ino` (default D11/D12). Change them to match your board
> and re-flash — RadioHead needs the pins fixed at compile time. The antenna
> (17.3 cm wire in each module's `ANT` hole) matters more than the exact pins: without
> it, 433 MHz barely reaches a few centimetres.

### FS1000A Transmitter (433 MHz)

| FS1000A pin | Arduino Nano pin | Notes |
|-------------|------------------|-------|
| DATA | D12 (default `RADIO_TX_PIN`) | ASK modulation signal |
| VCC | 5V | Module draws ≈20 mA during TX |
| GND | GND | Common ground |
| ANT | - | Solder a **17.3 cm** wire for quarter-wave monopole antenna |

---

### MX-RM-5V Receiver (433 MHz)

| MX-RM-5V pin | Arduino Nano pin | Notes |
|--------------|------------------|-------|
| DATA | D11 | ASK demodulated signal |
| VCC | 5V | Module draws ≈5 mA during RX |
| GND | GND | Common ground |
| ANT | - | Solder a **17.3 cm** wire for quarter-wave monopole antenna |

---

## Breadboard Layout

> The diagram below shows **Option B (divider)**. For the build in use here
> (**Option A: level converter + AMS1117**), see the HM-10 wiring section above —
> the D8→RXD line routes through the converter instead of the divider; TXD→D7 is
> direct in both options.

```
      Arduino Nano
      ┌─────────┐
   5V │ ●       │
  GND │ ●       │
   D7 │ ●       │  (BLE RX ← HM-10 TXD, direct)
   D8 │ ●       │  (BLE TX → voltage divider → HM-10 RXD)
  D11 │ ●       │  (Radio RX ← MX-RM-5V DATA, direct)
  D12 │ ●       │  (Radio TX → FS1000A DATA, direct)
      └─────────┘
        │   │   │   │   │   │
        │   │   │   │   │   │
        │   │   │   │   │   └─── D12 (TX) ──────────────────── FS1000A DATA
        │   │   │   │   │
        │   │   │   │   └─────── D11 (RX) ────────────────── MX-RM-5V DATA
        │   │   │   │
        │   │   │   └─────────── D8 (TX) ────── row 10 col a ── 1 kΩ ── row 10 col e
        │   │   │                                                     │
        │   │   │                                                row 12 col e ── 2 kΩ ── GND rail (blue)
        │   │   │                                                     │
        │   │   │                                                HM-10 RXD
        │   │   │
        │   │   └──── D7 (RX) ────────────────────────────── HM-10 TXD (direct, no divider)
        │   │
        │   └──────── GND rail (blue) ───────── HM-10 GND, FS1000A GND, MX-RM-5V GND
        │
        └─────────── 5V rail (red) ────────── HM-10 VCC*, FS1000A VCC, MX-RM-5V VCC

  * HM-10 VCC: check module tolerance; may require 3.3V instead of 5V.
```

---

## Power

The whole node runs off a single **5 V** rail. On the bench, power the Nano from **any USB
source** (a spare Mac port, a hub, or a phone charger) — the phone node talks to the iPhone
over BLE, so its USB is used only for power, not data.

Distribution from the Nano 5V pin:
- +5 V rail → AMS1117 VIN, HM-10 VCC, level converter HV, FS1000A VCC, MX-RM-5V VCC
- AMS1117 OUT (3.3 V) → level converter LV
- GND rail → common to all

Current budget: HM-10 ≈40 mA + FS1000A ≈20 mA + MX-RM-5V ≈5 mA + Nano ≈30 mA ≈ 100 mA
typical — well within USB's 500 mA. For field use later, a USB powerbank (5 V, ≥1 A) gives
many hours; a 5000 mAh pack ≈ 12 h.

---

## HM-10 BLE Configuration

### Factory Defaults
- **Baud rate**: 9600
- **BLE name**: `HM-10` or `BT05` (varies by vendor)
- **GATT Service**: `FFE0`
- **GATT Characteristic**: `FFE1` (RX/TX)

No AT-mode configuration is required for this sketch. The HM-10 will advertise the standard
UART service and accept connections on the default 9600 baud serial port.

### iOS Connection (CoreBluetooth, SwiftUI)

iOS apps connect to the HM-10 using CoreBluetooth:

1. **Scan** for peripherals advertising service `FFE0`.
2. **Connect** to the device (BLE name `HM-10` or `BT05`).
3. **Write** text lines (terminated by `\n` or `\r`) to characteristic `FFE1`.
4. **Read** (or subscribe to notifications on) characteristic `FFE1` for radio messages.

The RadioBleBridge sketch reads lines from HM-10 UART and sends them via 433 MHz radio;
incoming radio messages are echoed back to the BLE UART (terminated by `\n`).

---

## Addressing

Node addresses (this node's and the peer's) are configured dynamically from the phone over BLE
and stored in EEPROM. No firmware rebuild is required. Use the commands described in the
section below: `/addr`, `/peer` and `/show`.

---

## Address Configuration (over BLE)

Node addresses are not hard-coded — they are set from the phone and stored in EEPROM
(they survive reboots). Lines starting with `/` are intercepted by the bridge
and are NOT sent over the radio:

- `/addr N` — this node's address (0–255), replies `ok addr=N`
- `/peer N` — the peer's address (0–255), replies `ok peer=N`
- `/show` — current addresses, replies `addr=N peer=M`
- unknown `/…` — replies `err unknown`

With a clean EEPROM the defaults node=2, peer=1 are applied (the messenger's phone
node), so a bridge with no settings flashed works with the station out of the box.
EEPROM format: byte 0 — marker `0xF3`, byte 1 — node, byte 2 — peer.

---

## Line Protocol

Messages are **line-oriented**, delimited by `\n` or `\r`:

**BLE → Radio:**
```
(BLE UART) "PING\n" → (radio) send to Node 1
```

**Radio → BLE:**
```
(radio recv from Node 1) "PONG" → (BLE UART) "PONG\n"
```

Maximum line length: **50 bytes** (including any terminator stripping).

---

## Antenna

> **Bench note:** for a first bring-up with the two nodes ~10 cm apart you can skip antennas
> entirely — ASK couples fine at that range. Add antennas for any real distance.

For 433 MHz ASK, both FS1000A and MX-RM-5V require a **17.3 cm quarter-wave monopole antenna**:

```
Copper wire, 17.3 cm, soldered to the ANT pad on each module.
```

Typical range: 50–100 meters line-of-sight, depending on antenna orientation and obstacles.

---

## Compile & Upload

### Via arduino-cli

```bash
arduino-cli compile --fqbn arduino:avr:nano:cpu=atmega328old firmware/RadioBleBridge
```

### Via Arduino IDE

1. Open `firmware/RadioBleBridge/RadioBleBridge.ino`.
2. Select board: **Arduino Nano**, processor: **ATmega328P (Old Bootloader)**.
3. Ensure **RadioHead** library is installed (Library Manager).
4. Click **Upload**.

---

## Testing

1. **Power on** the Arduino + HM-10 + radio stack via USB powerbank.
2. On iOS (via a SwiftUI app or third-party BLE terminal):
   - Connect to BLE device `HM-10` or `BT05`.
   - Select service `FFE0`, characteristic `FFE1`.
   - Send `"HELLO\n"`.
3. The message is transmitted via 433 MHz radio to Node 1.
4. If Node 1 replies (e.g., `"ACK\n"`), it appears in the BLE terminal.

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| HM-10 not visible in BLE scan | Check VCC (5V or 3.3V per module); verify GND connection; ensure module is powered |
| BLE connects but no data transfers | Verify SoftwareSerial pins (D7 RX, D8 TX) and voltage divider; check HM-10 baud rate (factory 9600) |
| Radio messages not arriving | Check antenna soldering; verify FS1000A/MX-RM-5V pin connections (D12 TX, D11 RX); test line length ≤50 bytes |
| Random garbled characters in BLE | Power supply noise; ensure clean 5V rail and separate GND for radio modules |

---

## Related Documents

- **FemusFirmata:** `firmware/README.md` — main board firmware with Firmata + radio support.
- **HC-05 Bluetooth:** `docs/devices/hc05-bluetooth.md` — wireless Firmata over HC-05.
