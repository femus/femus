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
- Date: (pending)
- OS: (pending)
- Board: (pending)
- Port: (pending)
- Result: (pending)

