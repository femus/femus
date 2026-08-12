# FemusFirmata Firmware

## What and Why

FemusFirmata is a [ConfigurableFirmata](https://github.com/firmata/ConfigurableFirmata) sketch
that bundles the standard Firmata feature set (digital I/O, analog inputs, I2C) together with a
custom HX711 load-cell module and a 433 MHz ASK radio link (RadioHead RH_ASK).
Flash it once; the femus PHP framework drives everything over serial — no second sketch required.

## Required Libraries

Install via the Arduino Library Manager or arduino-cli:

| Library | Version tested |
|---------|---------------|
| ConfigurableFirmata | 3.3.0 |
| HX711 (RobTillaart) | 0.6.4 |
| RadioHead | 1.143.1 |

## Flashing via Arduino IDE

1. Open `firmware/FemusFirmata/FemusFirmata.ino`.
2. Install **ConfigurableFirmata** and **HX711** through the Library Manager
   (*Sketch → Include Library → Manage Libraries…*).
3. Select board: **Arduino Nano**, processor: **ATmega328P (Old Bootloader)**.
4. Click **Upload**.

## Flashing via arduino-cli

```bash
arduino-cli core install arduino:avr
arduino-cli lib install ConfigurableFirmata HX711 RadioHead
arduino-cli compile --fqbn arduino:avr:nano:cpu=atmega328old firmware/FemusFirmata
arduino-cli upload  --fqbn arduino:avr:nano:cpu=atmega328old -p <port> firmware/FemusFirmata
```

Replace `<port>` with the serial device (e.g. `/dev/ttyUSB0` on Linux,
`/dev/cu.usbserial-*` on macOS, `COM3` on Windows).

## Sysex Protocol (command byte 0x0E)

All frames use the standard Firmata sysex envelope:
`0xF0 <command> [payload…] 0xF7`

| Sub-command | Direction | Payload | Description |
|-------------|-----------|---------|-------------|
| `0x00` ATTACH | Host → Board | `dout_pin sck_pin` | Configure and start the HX711 |
| `0x01` READING | Board → Host | 5 septets (7-bit bytes) of `int32` value, little-endian | Raw ADC reading, sent every 100 ms |

### Reading encoding

The 32-bit raw value is encoded into 5 × 7-bit septets (LSB first):

```
septet[i] = (value >> (7 * i)) & 0x7F    (i = 0..4)
```

The PHP decoder reconstructs:

```
value = septet[0] | (septet[1] << 7) | (septet[2] << 14) | (septet[3] << 21) | (septet[4] << 28)
```

Sign is preserved because `int32_t` is cast to `uint32_t` before encoding and cast back on
the PHP side.

## Wiring (HX711 → Arduino Nano)

| HX711 pin | Arduino Nano pin |
|-----------|-----------------|
| VCC | 5V |
| GND | GND |
| DT (DOUT) | D3 |
| SCK | D2 |

Send `ATTACH 0x03 0x02` (dout=3, sck=2) from PHP to activate the sensor.

## Reporting Interval

The HX711 module emits one reading every **100 ms** (`FEMUS_HX711_INTERVAL_MS`), independent
of the Firmata sampling interval. The board skips a reading if the HX711 is not ready (i.e.
the previous conversion is still in progress).

---

## Radio Module (sysex command byte `0x0D`)

433 MHz ASK link using FS1000A (TX) and MX-RM-5V (RX) modules driven by the RadioHead `RH_ASK`
driver. Addressing follows the RadioHead header scheme: node addresses 0–126, broadcast address 127.
Maximum payload is **50 bytes** per message.

All frames use the standard Firmata sysex envelope: `0xF0 0x0D [sub-command] [payload…] 0xF7`

### Sub-commands

| Sub-command | Direction | Payload | Description |
|-------------|-----------|---------|-------------|
| `0x00` ATTACH | Host → Board | `address rxPin txPin` | Init radio; speed fixed at 2000 bps |
| `0x01` SEND | Host → Board | `toAddress` + 7-bit pairs of message bytes | Transmit to `toAddress` (0x7F = broadcast) |
| `0x02` RECV | Board → Host | `fromAddress toAddress` + 7-bit pairs of message bytes | Received message; 0x7F = broadcast |

### Address space

| Value | Meaning |
|-------|---------|
| 0–126 | Unicast node address |
| 127 (`0x7F`) | Broadcast (all nodes) |

Internally `0x7F` maps to `RH_BROADCAST_ADDRESS` (0xFF); the translation is done inside the firmware.

### Payload encoding (SEND / RECV)

Each message byte is split into a 7-bit pair (LSB first, high bit second), matching the standard
femus/Firmata encoding used by all other sysex frames:

```
byte  →  (byte & 0x7F)  (byte >> 7 & 0x01)
```

Maximum decoded payload: **50 bytes** (`FEMUS_RADIO_MAX_LEN`).

### Antenna

For 433 MHz a quarter-wave monopole antenna is **17.3 cm** of wire soldered to the ANT pad.

### Wiring (radio → Arduino Nano)

| Module pin | Arduino Nano pin | Notes |
|------------|-----------------|-------|
| FS1000A DATA | D12 (default txPin) | Pass txPin in ATTACH |
| MX-RM-5V DATA | D11 (default rxPin) | Pass rxPin in ATTACH |
| VCC (both) | 5V | |
| GND (both) | GND | |

Send `ATTACH address rxPin txPin` (e.g. `0x01 0x0B 0x0C`) from PHP to activate the radio.

### Reception

`RadioFeature::report()` is called on every `loop()` iteration (not rate-limited) so that
incoming ASK packets — which arrive in a tight bit-bang ISR window — are drained as fast as
possible. Missed packets are not retransmitted; implement acknowledgement at the application layer
if reliability is required.

## Flashing Firmware: `femus firmware:flash`

No Arduino IDE required. All you need is [arduino-cli](https://arduino.github.io/arduino-cli/latest/installation/)
(`brew install arduino-cli`). Prebuilt firmware images live in `firmware/build/*.ino.hex`
and ship with the package — by default the hex is flashed as-is: no compilation,
no library installation; femus installs only the `arduino:avr` core (for avrdude).

```bash
# phone node (BLE⇄radio bridge)
vendor/bin/femus firmware:flash radio-bridge

# station (ConfigurableFirmata + Radio + HX711)
vendor/bin/femus firmware:flash femus
```

Flags:
- `--port=/dev/cu.usbserial-XXXX` — explicit port (default `auto`, detected automatically).
- `--fqbn=arduino:avr:nano` — for a Nano with the new bootloader (default is
  `arduino:avr:nano:cpu=atmega328old` — a board with the Old Bootloader). The hex is the same either way:
  the bootloader only affects upload speed, not the firmware itself.
- `--build` — build from source instead of using the prebuilt hex (needed after editing
  the sketches; femus installs the libraries itself: ConfigurableFirmata, RadioHead, HX711).

After changing a sketch, rebuild the hex files shipped with the package:

```bash
arduino-cli compile --fqbn arduino:avr:nano firmware/FemusFirmata --output-dir firmware/build
arduino-cli compile --fqbn arduino:avr:nano firmware/RadioBleBridge --output-dir firmware/build
```
