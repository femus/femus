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
- Date: (pending)
- Board: (pending)
- Result: (pending)

---

## Release 2026-08-04-hx711

### Testing Checklist (Pending Human Execution)

1. Flash firmware/FemusFirmata (see firmware/README.md) — blink/button examples still work (regression)
2. Wire HX711 per docs/devices/hx711.md, run `php examples/scale.php` — raw deltas react to pressing the load cell
3. Calibrate with a known weight (e.g. a 100 g weight or 6.1 g five-ruble coin stack), verify grams
4. Record results below

### Run 1
- Date: (pending)
- Board: (pending)
- Result: (pending)

