# RadioBleBridge (BLE + 433 MHz Radio Node)

Phone node (Node B) of the femus radio messenger. Bridges iPhone Bluetooth (HM-10 BLE module)
to a 433 MHz ASK radio (FS1000A TX + MX-RM-5V RX). A standalone sketch with no Firmata — each
line received from BLE is sent via radio, and each radio message is echoed to BLE.

---

## Wiring to Arduino Nano

### HM-10 (Bluetooth Low Energy)

| HM-10 pin | Arduino Nano pin | Notes |
|-----------|------------------|-------|
| VCC | 5V or 3.3V* | Depends on board: 5V-tolerant modules use 5V; others use 3.3V regulator output |
| GND | GND | Common ground |
| TXD | D7 (SoftwareSerial RX) | Direct connection (3.3V signal is safe on AVR input) |
| RXD | D8 (SoftwareSerial TX) | **Through 1k/2k voltage divider** (Arduino TX is 5V; HM-10 RXD is 3.3V-tolerant only) |

**Voltage divider on Arduino D8 → HM-10 RXD line:**

```
Arduino D8 (TX, 5V) ──── 1 kΩ ──── HM-10 RXD
                                        │
                                       2 kΩ
                                        │
                                       GND
```

The divider produces ~3.33 V at the HM-10 RXD pin: `5V × 2kΩ / (1kΩ + 2kΩ) ≈ 3.33V`.

*HM-10 default VCC varies. Check your module: 5V-tolerant versions connect to Arduino 5V;
others require the 3.3V regulator output (often available on Nano).

---

### FS1000A Transmitter (433 MHz)

| FS1000A pin | Arduino Nano pin | Notes |
|-------------|------------------|-------|
| DATA | D12 | ASK modulation signal |
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

**USB powerbank** (5V, ≥1A) supplies:
- Arduino Nano: ~50 mA idle + ≈200 mA during transmit/BLE activity
- HM-10: ≈50 mA
- FS1000A: ≈20 mA during TX
- MX-RM-5V: ≈5 mA during RX

**Total:** ~300 mA typical, ≈400 mA during active radio burst. A 5000 mAh powerbank gives
≈12 hours of field operation.

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

The RadioBleBridge node is **Node 2** (hardcoded `NODE_ADDRESS 2`).
It communicates with **Node 1** (hardcoded `PEER_ADDRESS 1`), typically the main femus board.

- All BLE-to-radio messages are sent **to Node 1**.
- All radio messages **from Node 1** are forwarded to BLE.

To add more peers, modify `PEER_ADDRESS` and rebuild.

---

## Конфигурация адресов (по BLE)

Адреса узлов не зашиты в код — задаются с телефона и хранятся в EEPROM
(переживают перезагрузку). Строки, начинающиеся с `/`, перехватываются мостом
и НЕ уходят в радио:

- `/addr N` — свой адрес узла (0–255), ответ `ok addr=N`
- `/peer N` — адрес собеседника (0–255), ответ `ok peer=N`
- `/show` — текущие адреса, ответ `addr=N peer=M`
- неизвестная `/…` — ответ `err unknown`

При чистом EEPROM применяются дефолты node=2, peer=1 (телефонный узел
мессенджера), поэтому незалитый настройками мост сразу работает со станцией.
Формат EEPROM: байт 0 — маркер `0xF3`, байт 1 — node, байт 2 — peer.

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
