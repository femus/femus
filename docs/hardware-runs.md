# Hardware Testing Runs

## Release 2026-08-02-femus-core-foundation

### Testing Checklist (Pending Human Execution)

Manual hardware verification with live Arduino is pending. When executed, verify the following:

1. Arduino flashed with StandardFirmata (Arduino IDE → File → Examples → Firmata → StandardFirmata → Upload)
2. Serial port identified (macOS: `ls /dev/cu.*`, Linux: `ls /dev/ttyUSB* /dev/ttyACM*`)
3. Run `php examples/blink.php <port>` — built-in LED blinks once per second
4. Connect button (wiring: KY-004 S→D2, −→GND) and run `php examples/button-led.php <port>` — LED lights on button hold
5. Record results below with date, OS, board model, and port

**Status**: Awaiting hardware verification. Record runs below.

---

### Run 1
- Date: 2026-08-04
- OS: macOS (Darwin 25.5)
- Board: Arduino Nano (ATmega328P; bootloader is the NEW one despite the 2011 board — flash with plain "ATmega328P" processor option, not Old Bootloader)
- Port: /dev/cu.usbserial-A50285BI (FT232, found by auto-discovery)
- Result: blink.php PASS — LED blinks. Two real bugs caught and fixed during this run:
  1. examples defaulted to Linux-only /dev/ttyUSB0 instead of auto-discovery (b904864)
  2. macOS termios reset: stty ran before fopen so the baud never stuck (garbled bytes), and the 3s probe timeout was shorter than the Nano's ~3.7s boot (652a5a2)
- button-led.php: PASS — bare tact switch on D12 + right-side GND (no external resistor, internal pull-up), hold → LED on, release → off. Root cause of the first failed attempt: a power/GND wire was not actually connected. Release 2026-08-02 checklist: COMPLETE ✓

---

## Release 2026-08-03-analog-i2c

### Testing Checklist (Pending Human Execution)

1. `php examples/water-level.php <port>` — water level sensor on A0, percentages change as sensor is dipped
2. `php examples/lcd-clock.php <port>` — LCD shows "femus" on line 1 and ticking time on line 2
3. `php examples/gyro-dump.php <port>` — GY-521, z ≈ +1g when flat on table, responds to tilt
4. Record results below

### Run 1
- Date: 2026-08-04
- Board: Arduino Nano (StandardFirmata), macOS host
- Result: lcd-clock-parallel.php PASS — QAPASS 1602A (no backpack) via the parallel driver:
  "femus" + ticking clock. Field notes: display must be powered when the script starts
  (init sequence is lost otherwise — a row of black boxes means "restart the script");
  a backlight wire landed on a signal pin first (backlight blinked in sync with the
  clock — moved to 5V). I2C lcd-clock.php: not testable (owner's LCD has no backpack).
  water-level / gyro-dump: pending

---

## Release 2026-08-04-hx711

### Testing Checklist (Pending Human Execution)

1. Flash firmware/FemusFirmata (see firmware/README.md) — blink/button examples still work (regression)
2. Wire HX711 per docs/devices/hx711.md, run `php examples/scale.php` — raw deltas react to pressing the load cell
3. Calibrate with a known weight (e.g. a 100 g weight or 6.1 g five-ruble coin stack), verify grams
4. Record results below

### Run 1
- Date: 2026-08-04
- Board: Arduino Nano + FemusFirmata (ConfigurableFirmata 3.3.0 + custom Hx711Feature)
- Wiring: XFW-HX711, DT=D4 SCK=D5 (scale.php pin args), bar load cell red/black/white/green → E+/E−/A−/A+
- Result: scale.php PASS — auto-tare works, raw readings stream at 10 Hz and react to
  pressing the bar (±100 raw units of finger pressure/noise around zero). The full
  custom-firmware path (C++ Hx711Feature → femus sysex 0x0E → PHP LoadCell) is
  verified on hardware. Calibration with a known weight: pending

---

## Release 2026-08-04-gsm-at

### Testing Checklist (Pending Human Execution)

1. Power the modem correctly (SIM800L: external 3.4–4.2 V source, common GND — see docs/devices/gsm-modem.md)
2. Insert a SIM (PIN disabled), connect via USB-TTL, `php examples/sms-send.php <port> <your number> "test"`
3. SMS arrives on the phone; reply to it — onSmsReceived demo prints it
4. Record results below

### Run 1
- Date: (pending)
- Modem: (pending)
- Result: (pending)

---

## Release 2026-08-04-radio

### Testing Checklist (Pending Human Execution)

1. Flash both Arduino Nano boards with FemusFirmata (see firmware/README.md)
2. Solder 17.3 cm antenna wires to ANT pads on both FS1000A and MX-RM-5V modules (critical for range)
3. Wire radio modules per docs/devices/radio-433.md (FS1000A DATA→D12, MX-RM-5V DATA→D11)
4. Run radio-chat on two stations:
   - Terminal 1: `php examples/radio-chat.php <port1> 1 2`
   - Terminal 2: `php examples/radio-chat.php <port2> 2 1`
5. Exchange messages between nodes; verify round-trip delivery
6. Test integration with Node B (RadioBleBridge): connect Node A to Node B via 433 MHz, send/receive via BLE
7. Record results below

### Run 1
- Date: (pending)
- Board: Arduino Nano + FemusFirmata (radio support)
- Wiring: FS1000A DATA→D12 VCC→5V GND→GND, MX-RM-5V DATA→D11 VCC→5V GND→GND
- Antenna: 17.3 cm copper wire on ANT pads of both modules
- Result: (pending)

## Release 2026-08-06-radio-messenger (bench bring-up)

Full two-node messenger on the bench: both nodes USB-powered, ~10 cm apart, **no antennas**.
Node B uses DSD TECH HM-10 (BLE) + AMS1117-3.3 + BSS138 level converter (Option A).

### Node A — station (talks to Mac over USB)
1. [ ] Wire FS1000A: DATA→D12, VCC→5V, GND→GND
2. [ ] Wire MX-RM-5V: DATA→D11, VCC→5V, GND→GND
3. [ ] Flash: `vendor/bin/femus firmware:flash femus` (auto-installs core + libs)
4. [ ] Leave Node A plugged into the Mac by USB

### Node B — phone node (talks to iPhone over BLE)
1. [ ] Confirm the BLE module is **HM-10** (BLE), not HC-05 (Classic)
2. [ ] Power rail: Nano 5V → +5V rail; Nano GND → GND rail
3. [ ] AMS1117-3.3: VIN←+5V, GND←GND, OUT→3.3V mini-rail (LV reference)
4. [ ] HM-10: VCC←+5V, GND←GND
5. [ ] Level converter: HV←+5V, LV←3.3V(AMS1117), both GND←GND
6. [ ] Level converter channels: HV1←D8 / LV1→HM-10 RXD ; HV2←D7 / LV2→HM-10 TXD
7. [ ] Wire FS1000A: DATA→D12, VCC→5V, GND→GND
8. [ ] Wire MX-RM-5V: DATA→D11, VCC→5V, GND→GND
9. [ ] Flash: `vendor/bin/femus firmware:flash radio-bridge`
10. [ ] Power Node B from any USB (spare Mac port / charger)

### Bring-up
1. [ ] Node A: `php examples/radio-chat.php <port> 1 2` (station = node 1 → peer 2)
2. [ ] iPhone: build & run FemusRadioTerminal (see ios/FemusRadioTerminal/README.md); it auto-connects to service FFE0
3. [ ] In the app send `/show` → expect `addr=2 peer=1` (defaults match — no config needed)
4. [ ] Send a message from the app → appears in the Mac terminal
5. [ ] Send a message from the Mac terminal → appears in the app
6. [ ] Demo: turn off Wi-Fi/cellular → chat still works

### Run 1
- Date: (pending)
- Nodes: A = Nano+FemusFirmata (USB→Mac); B = Nano+RadioBleBridge + HM-10 + AMS1117 + level converter (USB power)
- Antennas: none (bench, ~10 cm)
- Result: (pending)

