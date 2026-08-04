# FemusFirmata Firmware

## What and Why

FemusFirmata is a [ConfigurableFirmata](https://github.com/firmata/ConfigurableFirmata) sketch
that bundles the standard Firmata feature set (digital I/O, analog inputs, I2C) together with a
custom HX711 load-cell module. Flash it once; the femus PHP framework drives everything over
serial — no second sketch required.

## Required Libraries

Install via the Arduino Library Manager or arduino-cli:

| Library | Version tested |
|---------|---------------|
| ConfigurableFirmata | 3.3.0 |
| HX711 (RobTillaart) | 0.6.4 |

## Flashing via Arduino IDE

1. Open `firmware/FemusFirmata/FemusFirmata.ino`.
2. Install **ConfigurableFirmata** and **HX711** through the Library Manager
   (*Sketch → Include Library → Manage Libraries…*).
3. Select board: **Arduino Nano**, processor: **ATmega328P (Old Bootloader)**.
4. Click **Upload**.

## Flashing via arduino-cli

```bash
arduino-cli core install arduino:avr
arduino-cli lib install ConfigurableFirmata HX711
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
