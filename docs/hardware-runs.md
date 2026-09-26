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

### Testing Checklist (done — Runs 1–2)

1. Power the modem correctly (SIM800L: external 3.4–4.2 V source, common GND — see docs/devices/gsm-modem.md)
2. Insert a SIM (PIN disabled), connect via USB-TTL, `php examples/sms-send.php <port> <your number> "test"`
3. SMS arrives on the phone; reply to it — onSmsReceived demo prints it
4. Record results below

### Run 1
- Date: 2026-09-19
- Modem: SIMCOM A7670G-LABE (CAT1_A767x board), firmware V1.11.2, LTE Cat-1
- Power: the modem's own micro-USB (5 V, 2 A capable). The 5-12V / PWR-K / SLEEP pins stay empty;
  a USB-TTL adapter cannot power it — peaks reach 2 A.
- Wiring: YP-01 (PL2303) ↔ 2-channel level converter ↔ modem header. The modem UART is **1.8 V**,
  so the converter is mandatory; its LV pin is fed from a 3V3 → 1 kΩ → 1 kΩ → GND divider (≈1.65 V).
  Crossed signals: YP-01 TXD → converter → modem URX, modem UTX → converter → YP-01 RXD.
  Common GND on all three boards.
- Result: ✅ `AT` → `OK` at 115200. `ATI` reports A7670G-LABE.
  `+CPIN: READY`, `+CSQ: 24`, `+CREG: 0,1`, `+CEREG: 0,1`, `+COPS: 0,2,"302610",7` (LTE).
  `php examples/sms-send.php /dev/cu.usbserial-130 +1XXXXXXXXXX "Hello from femus"` → `SMS sent.`,
  delivered to the phone. `AT+CCID` returns ERROR on this firmware — use `AT+CICCID` for the ICCID.
- Data over LTE: the SIM's default bearer is `ota.bell.ca`, a carrier service APN with no internet —
  ping and the modem's own HTTP stack fail there (`+HTTPACTION: 0,706,0`). A second context with the
  consumer APN attaches and resolves DNS (`AT+CGDCONT=2,"IP","pda.bell.ca"` → `AT+CGACT=1,2` →
  `AT+CDNSGIP` returns real addresses), but this firmware rejects `AT+HTTPPARA="CID",2`, so the
  built-in HTTP client cannot be pointed at it. Do not chase this: on the Pi the modem is meant to
  come up as a USB network interface, and normal sockets replace the AT HTTP stack. Changing the
  **default** context APN gets registration denied — recover with `AT+CGDCONT=1,"IPV4V6",""` + `AT+CRESET`.
- Bring-up gotchas (all three cost an evening): GND of the USB-TTL adapter not connected at all;
  both divider resistors tied to GND, leaving LV at 0 V so the converter passed nothing;
  UTX/URX swapped. Symptom of every one of them is identical — not a single byte at any baud rate.
  If the port stays silent on all bauds, the fault is wiring, not the baud rate.

### Run 2 — SMS gateway end to end
- Date: 2026-09-21
- Setup: same bench as Run 1 (A7670G over YP-01 + level converter, `/dev/cu.usbserial-130`).
  `GEMINI_API_KEY=... SMS_ALLOWED=+1... php examples/sms-gateway.php /dev/cu.usbserial-130`
- Result: ✅ a phone with mobile data and Wi-Fi off texts the SIM, `SmsGateway` routes the message,
  and the reply arrives as SMS: `/ping` → `pong`, `/weather Halifax` → live Open-Meteo forecast,
  a free-form question → answer from the live Gemini free tier via `OpenAiCompatibleAiClient`.
  Cyrillic works in both directions.
- Gotchas found (all fixed in code):
  - Non-Latin incoming text arrives as UCS-2 hex (`0421…`) — decoded by `Femus\Gsm\Ucs2`.
  - The modem rejects a UCS-2 body (`Invalid text mode parameter`) until `AT+CSCS="UCS2"`, and in
    that mode the **recipient number must be hex-encoded too**.
  - A UCS-2 SMS holds 70 characters, not 160 (`SMS size more than expected`) — long replies are split.
- SIM storage fills up (`+SMS FULL`, 20/20) within about a day of testing. New messages then wait
  at the carrier and arrive in a burst after `AT+CMGD=1,4`. Clear it before a demo;
  `femus modem:probe` warns when storage is nearly full.
- `femus modem:probe` also run on the bench with the SIM tray empty; it now reports that case
  instead of failing.

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
- Date: 2026-08-06
- Node B built & verified in isolation via nRF Connect (BLE `/show` on FFE1 → reply `addr=2 peer=1`).
- **Bootloader:** Node B's Nano is NEW bootloader → flash with `--fqbn=arduino:avr:nano:cpu=atmega328`
  (default `atmega328old` gives `not in sync`).
- **Wiring fix:** `HM-10 TXD → Arduino D7` wired DIRECT (3.3 V→5 V needs no shifting). Level
  converter Chan1 on this line was miswired and is not needed; converter is only required on
  `D8 → HM-10 RXD` (5 V→3.3 V). Reply path (D8→converter→RXD) confirmed via a heartbeat test sketch.
- Node A (station) + full end-to-end (radio A↔B, iPhone app): pending.
- Result: **Node B OK.**

### Run 2 — full end-to-end (2026-09-17)
- Actual bench wiring (differs from the sketch defaults): **Node A** rx **D3**, tx **D4**, address 1
  (D11 on this Nano is dead); **Node B** rx D11, tx **D10**, address 2. Node A talks to the Mac
  through a YP-01 USB-TTL (`/dev/cu.usbserial-130`), Node B is on `/dev/cu.usbserial-A50285BI`.
- iPhone app: the 7-day personal-team profile had expired; rebuilt and reinstalled from the Mac with
  `xcodebuild … -allowProvisioningUpdates` + `xcrun devicectl device install app`, then trusted the
  developer profile on the phone (Settings → General → VPN & Device Management).
- `/show` over BLE → `addr=2 peer=1`.
- Mac → iPhone: 32 of 35 pings received (5 s interval). Before re-seating the antennas it was ~1 in 5.
- iPhone → Mac: **0 packets on 16 candidate rx pins** until the owner pressed the loose antenna wires
  and module legs back in — then 3/3 (`Hi`) and a reply mid-ping-stream. Root cause: a loose antenna /
  breadboard contact on Node B's transmitter, not pins or firmware.
- Lesson: when one direction dies, check the 17.3 cm antenna wires first — they slip out of the ANT
  holes at a touch. Consider soldering them for the demo.
- Note: Node B's bootloader did not sync over `A50285BI` (avrdude `sync byte 0x14 but got 0x74/0xff`
  at 115200, no response at 57600); its flash was left untouched.
- Result: **Messenger end-to-end OK.**
- Same evening: `examples/radio-web-chat.php` (browser chat) verified on the bench — messages both ways between the iPhone app and the page.

### 2026-09-18 — serial transport moved to sanchescom/php-serial 3.0.0
- `Femus\Transport\SerialPort` / `SerialPortLocator` are now thin adapters over the package
  (same constructor, same `Transport` interface, `SerialException` mapped to `TransportException`).
- Live check on Node A (Nano via YP-01, `/dev/cu.usbserial-130`): `femus scan` → Firmata board,
  femus-ready; LED blink on D13 for 6 s through the new transport — clean run and exit.
- 238 tests green.


---

## Release 2026-08-11-no-hardware-batch (pantry, KY-040, DS18B20, 74HC595)

All four drivers were written without hardware access — fake-board tests only.
Everything below is **pending live verification**.

### Testing Checklist (Pending Human Execution)

**KY-040 rotary encoder** (StandardFirmata is enough)
1. [ ] Wire: CLK→D4, DT→D5, SW→D6, +→5V, GND→GND
2. [ ] `php examples/rotary-encoder.php <port>` — volume goes up clockwise, down counter-clockwise, one step per detent, press prints "Confirmed"
3. [ ] If direction is inverted, swap CLK/DT and note it here

**Smart pantry / PantryJar** (needs FemusFirmata + HX711; the LoadCell path itself
is already hardware-verified — see Release 2026-08-04-hx711 Run 1. New here:
gram calibration + the PantryJar layer on top)
1. [ ] Wire HX711 per docs/devices/hx711.md, adapt pins in examples/pantry.php
2. [ ] Tare empty, calibrate with a known weight, put a jar on — contents/percent/servings look sane
3. [ ] Pour some contents out — onChange fires, isLow() flips near the threshold

**DS18B20** (Raspberry Pi, w1-gpio overlay)
1. [ ] On femus-pi: enable 1-Wire (`dtoverlay=w1-gpio` + reboot), sensor on GPIO4 with 4.7k pull-up
2. [ ] `php examples/pi-temperature.php` — plausible room °C, warms up when the probe is held

**74HC595 shift register** (StandardFirmata is enough)
1. [ ] Wire per docs/devices/74hc595.md: DS→D2, SHCP→D3, STCP→D4, MR→5V, OE→GND, 8 LEDs on Q0..Q7
2. [ ] `php examples/shift-register.php <port>` — a single LED sweeps back and forth (Knight Rider)
3. [ ] All LEDs dark on script start (constructor flushes zeros — no power-up garbage)
4. [ ] If available, chain a second chip (Q7'→DS) and check `chips: 2` drives outputs 8-15

**Status**: Awaiting hardware verification. Record runs below.

---

## Release 2026-09-25-tea5767

Written without hardware; tests run against a fake I2C bus.

### Testing Checklist (Pending Human Execution)

1. Wire the module per docs/devices/tea5767.md (5V, GND, SDA→A4, SCL→A5), antenna wire in ANT, headphones in.
2. `vendor/bin/femus fm:scan` — the table lists the local stations; the strongest one plays in the headphones.
3. `vendor/bin/femus fm:tune <MHz>` on another listed station — it switches, the reported level is close to the scan's.
4. If every level reads the same, raise the settle time (`FmRadio` / `scan(settle:)`).
5. Record results below.

### Run 1
- Date: 2026-09-25, Halifax
- Board: Nano (FT232, `/dev/cu.usbserial-A50285BI`, the messenger's node B) reflashed from
  RadioBleBridge to FemusFirmata. `femus firmware:flash` failed before touching the board:
  the avrdude bundled with arduino-cli is an Intel build and this Mac has no Rosetta
  (`bad CPU type in executable`). Flashed with Homebrew's native avrdude 8.3 instead:
  `avrdude -c arduino -p m328p -P <port> -b 115200 -U flash:w:firmware/build/FemusFirmata.ino.hex:i`
  — the write completed, the verify pass lost sync, `femus scan` then reported the board femus-ready.
- Result: ✅ the module answers at 0x60, `fm:tune 101.3` plays music in the headphones.
- Findings, fixed in code:
  - Empty air reads 6–9 on the chip's level meter, real stations 10–13. A fixed threshold of 7
    listed 45 "stations"; the bar is now the band's median level + 3 → 12 stations, matching
    the local dial (90.5 CBC Radio One, 101.3, 104.3 Q104, 95.6 ≈ News 95.7…).
  - The first reading after power-up is junk (87.5 MHz at level 15) — `scan()` now drops it.
  - Settle time 50 ms and 200 ms give identical readings; 50 ms stays. A full scan takes ~17 s,
    the I2C round trip over Firmata dominates.
- Open: the stereo flag never came up, even at level 13 — antenna or signal strength, not checked yet.
- ✅ `php examples/fm-spectrum.php <port>`: the sweep fills the chart in ~14 s, 13 stations rise
  above the noise floor (101.9 and 102.7 among them, which the fixed threshold used to miss);
  clicking a station plays it (102.7, 101.3 at level 13), Resume sweep carries on.
- First live start crashed with `Write stalled`: a one-shot timer stayed registered while its
  callback ran, the blocking I2C read inside it ticked the loop, and the loop fired the same timer
  again — recursion until the serial buffer choked. Fixed in `StreamSelectLoop::fireDueTimers()`
  (retire before calling), with a test.
- After that crash the TEA5767 held the I2C bus: Firmata answered, the module did not. A board reset
  does not clear it — unplugging the Nano's USB (power-cycling the module) does.
- Right after power-up `fm:tune` reported level 1 — the same junk first reading; it is dropped now.
