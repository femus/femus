# Hardware Inventory (project test bench)

Identified from the owner's photos, 2026-08-03. This is the femus test fleet:
every module is a future driver and an item on the hardware checklist.

## Controller Boards

| Board | Chip | Connection | Role |
|---|---|---|---|
| Arduino Nano V3 ("LISA 2011") | ATmega328P | mini-USB (CH340) | **primary Firmata adapter board**; in the Arduino IDE select Processor: "ATmega328P (Old Bootloader)" |
| Arduino Pro Mini | ATmega328P | via USB-TTL | spare; YP-01 has no DTR → flashing requires a manual reset |
| Raspberry Pi 4 Model B | BCM2711 | standalone | Linux adapter (plan 2) |
| ESP-01S | ESP8266EX | UART | future: Firmata-over-WiFi / standalone node |
| Digispark | ATtiny85 | micro-USB (V-USB) | out of scope (8 KB, Firmata doesn't fit) |
| Unmarked black board | STM8S105K4T6 | SWIM (requires ST-Link) | out of scope for v1 |

## Modules by femus Class

### UART / AT (Uart class, plans 5+)
- **SIM800L** (red breakout) — GSM 2G. ⚠️ Power 3.4–4.2 V, current spikes up to 2 A —
  do NOT connect to the Arduino 5V pin; a separate supply is required. Antenna is mandatory.
- **SIM5216E** on a SIMCOM WCDMA EVB board — 3G/WCDMA, RS232+USB, SIM slot.
  ⚠️ Verify that the carrier still operates a 3G network.
- **Beitian BN-220** — GPS, UART/NMEA 9600, future Gps driver.
- **HC-05/06** — Bluetooth-UART bridge (logic 3.3V, power 3.6–6 V).
  Idea: wireless Firmata (Arduino ↔ PHP without a cable).
- **YP-01 USB-TTL** — adapter for connecting UART modules directly to a computer
  (no DTR — inconvenient for flashing the Pro Mini).

### I2C (plan 3)
- **TEA5767** (blue board) — FM receiver, pins +5V/SDA/SLC/GND.
- LCD 1602, GY-521 (MPU-6050), DS-1307 — from the Elegoo kit (see kit photos).

### Timing-critical (plan 4)
- **HX711** (MH, pins E+/E−/A−/A+/B−/B+ and GND/DT/SCK/VCC) + load cell beams.

### Radio loops (mutual verification)
- **FS1000A (TX) + MX-RM-5V (RX) × 2 sets** (2 transmitters + 2 receivers) —
  433 MHz: a closed "sent → received" loop AND full duplex
  (TX+RX on each side) → a bidirectional radio link between two boards.
- **LCD_FM_TX_V2.2.1** (FM transmitter, JL chip, controlled via TX/RX pads = UART,
  micro-USB power, jack input + microphone) + **TEA5767** (FM receiver, I2C) →
  a complete human-free FM loop: TX broadcasts on a frequency → RX confirms lock.

### Elegoo Kit (37-in-1) — digital/analog
PIR HC-SR501, HC-SR04 ultrasonic, buttons, relays, buzzers, laser, photoresistor,
Hall sensor, tilt, vibration, flame, DHT11, joystick, encoder, 4x4 keypad,
water level, LCD1602, RGB LED, IR receiver/transmitter — these cover the
DigitalPin/AnalogPin classes and are already partially implemented (Button, MotionSensor, Relay,
Led, Buzzer in v1).

### Added 2026-08-03 (second batch of photos)
- **DFPlayer Mini** — MP3 player with microSD, controlled over UART (9600) — future
  AudioPlayer driver: voicing events ("motion detected" spoken aloud)
- **KY-040** — rotary encoder (CLK/DT/SW) — digital class, digital-extensions plan
- **MB102** — breadboard power supply module (VIN 7–12V → 3.3/5V, jumpers) — also to power the SIM800L
- **DS18B20** (waterproof probe + Plugable Terminal) — 1-Wire thermometer; on the Pi via the
  w1-therm overlay, on Arduino — the ConfigurableFirmata OneWire module
- **Cinterion EHS5-E** (EHS5/6/8 eval board) — second 3G modem with AT commands; same
  caveat about 3G network availability as the SIM5216E
- Starter kit: breadboard, MB102, resistors/capacitors, LEDs, buttons, buzzers,
  4N35 optocoupler, shift register (74HC595), transistors, potentiometer — supporting components

### Added 2026-08-03 (third batch of photos)
- **STM32MP157C-DK2 Discovery Kit** — Linux MPU board (2×Cortex-A7 650MHz + M4,
  4" TFT touch, WiFi/BT LE, Gigabit Ethernet, 40-pin RPi-compatible GPIO header).
  ⭐ Second target device for the Linux adapter (plan 2): same libgpiod as on the Pi
- **HY32D** — 3.2" TFT LCD (ILI932x, parallel 16-bit bus + touch) — outside the
  universal layer in v1 (like the OV7670); possible on STM32MP1/Linux via fbdev
- **mikroElektronika TFT Developer kit 3 (XMEGA)** — mikromedia + mikroC — a separate
  ecosystem, out of scope for femus
- **Cinterion DSB Multi Adapter A4** — carrier board for Cinterion modems
  (HC25/MC75/MC55/EHS5) — together with the EHS5-E this makes a complete GSM bench with AT access
- **Pinboard v1.1** (DI HALT) — AVR development board: ATmega16/32, LCD1602, buttons,
  DIP switches, encoder, FTDI-based USB-UART + bitbang programmer (FT BB PROG),
  JTAG/ISP. Docs on the owner's machine: ~/Downloads/pinboard_v11_start.pdf, Pinboard11_Tech_Info.pdf.
  Potential: flash the MightyCore Arduino core → StandardFirmata → a third Firmata board
- **Assembled breadboard**: Arduino Nano V3 + XFW-HX711 + HC-05 already wired up —
  a ready-made bench for plan 4 (LoadCell) and wireless Firmata

## Miscellaneous
- OV7670 camera (parallel bus — outside the universal layer, Linux-only path)
- WLAN mini-PCIe card, LiPo battery, power supplies, breadboard, wires

### Added 2026-08-04
- **Si4432 (EZRadioPRO)** — radio transceiver 240–930 MHz, FSK/GFSK/OOK (NOT LoRa,
  despite the seller's listing), TX up to +20 dBm. Receive AND transmit in one chip,
  half-duplex; hardware packet engine (CRC, sync, addressing). SPI, power strictly
  3.3V (TX up to ~85 mA → the MB102 3.3V rail, not a Nano pin). RadioHead RH_RF22 is
  a ready-made driver → candidate #1 for the femus radio module. A second one is needed for a link.
- **HM-10** — BLE-UART module (CC254x) — the "iPhone ↔ UART" bridge; the only
  option for iOS (SPP/HC-05 is closed off on iOS). The key component of the radio messenger.

### Added 2026-08-06 (radio messenger node B wiring)
- **HM-10 (DSD TECH)** — BLE 4.0 (CC2541), transparent case "Powered by DSD TECH",
  backplane `Power 3.6–6V` / `LEVEL:3.3V` (onboard regulator → VCC can be 5 V, logic is 3.3 V).
  Service FFE0 / characteristic FFE1, 9600 baud. Node B of the messenger.
- **HC-05 (DSD TECH)** — Bluetooth **Classic** (BC417), visually identical to the HM-10
  (same DSD TECH case). ⚠️ **Do not confuse with the HM-10**: iOS cannot see it. NOT used
  in the messenger (the station ↔ Mac link goes over USB). Kept as a spare for a separate HC-05 Firmata.
- **AMS1117-3.3** — LDO regulator, input 5 V → output 3.3 V (marked `T33`,
  up to ~800 mA, dropout ~1.1 V). In node B it provides the 3.3 V reference for the LV side
  of the level converter (the HM-10 itself runs off 5 V).
- **CJMCU level converter (BSS138)** — bidirectional level shifter,
  4 channels, HV(5 V)/LV(3.3 V)/GND on each side. Shifts the UART lines D7/D8 ↔
  HM-10 TXD/RXD in node B. An alternative to a 1k/2k divider.
