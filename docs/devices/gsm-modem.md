# GSM Modem (AT Commands)

This guide covers any AT-compatible GSM/GPRS modem: SIM800L, SIM5216E, Cinterion EHS5-E, and similar. The modem communicates over a serial port (USB-to-TTL adapter) using AT commands.

## Supported Modems

- **SIM800L** (very common, cheap, 2G-only — auto-baud, minimal power)
- **SIM5216E** (smaller footprint variant of SIM800)
- **Cinterion EHS5-E** (industrial, 3G, higher quality)
- Others with standard AT command set

## Power Supply (⚠️ Critical)

### SIM800L (most common)

**Voltage**: 3.4 – 4.2 V (**never the 5 V pin**)  
**Peak current**: 2 A during TX burst (RX ≈ 100 mA)

| Supply | ✓ Suitable | Notes |
|--------|-----------|-------|
| MB102 buck converter | ✅ | Set to 4.0 V output; has reverse-polarity protection |
| LiPo battery (1S–2S) | ✅ | Direct connection if voltage in range; common in hobby projects |
| Arduino 5 V pin | ❌ | Too high; modem will brown-out during TX |
| Arduino 3.3 V pin | ❌ | Too weak for 2 A bursts; modem will reset |

**Common ground is essential:** all GND lines (power supply, USB-TTL adapter, Arduino if present) must be connected.

## Wiring via USB-TTL Adapter

A common USB-TTL adapter (such as a CH340, FT232 or PL2303 breakout) provides:

| Adapter pin | Modem pin | Notes |
|-------------|-----------|-------|
| TX | RX | Cross-connect: adapter transmit → modem receive |
| RX | TX | Cross-connect: adapter receive → modem transmit |
| GND | GND | Common reference (critical) |

### SIM800L Level Shifting

The SIM800L logic operates at ~2.8 V. Standard USB-TTL adapters output 5 V TTL, which can damage the modem RX input.

**Solution: voltage divider on the adapter RX line:**

```
Modem TX (2.8 V out) ──── Adapter RX input
                                ↑
                           (no resistor needed)

Adapter TX (5 V out) ──┬── 1k resistor ──┬── Modem RX
                      │                  │
                      │              4.7k resistor
                      │                  │
                      └──────── GND ─────┘
```

This drops the 5 V signal to ~1.6 V (safe for 2.8 V input). If you lack resistors, a cheaper option is a logic level converter module (≈$1–2).

**Other modems** (SIM5216E, Cinterion) may have different logic levels — check the datasheet.

## Baud Rate

### SIM800L Auto-Baud

SIM800L auto-detects baud rate. On first connection, send `AT` at 115200. If no response, fall back to 9600:

```php
$modem = GsmModem::open($port, 115200);  // try 115200 first
// If timeouts occur, restart and use:
$modem = GsmModem::open($port, 9600);    // fall back to 9600
```

### Other Modems

Check the modem datasheet. Common rates: 9600, 19200, 115200.

## Hardware Setup Example (SIM800L + USB-TTL + Arduino)

```
     Power Supply (4.0 V)
            │
            ├─── MB102 +4.0V output
            │       ├─ SIM800L VCC
            │       └─ Resistor divider supply (if needed)
            │
            └─── MB102 GND
                    ├─ SIM800L GND (and all other grounds)
                    ├─ USB-TTL GND
                    └─ Arduino GND (if present)

     USB-TTL Adapter (plugged into computer)
            TX  ──── 1k resistor ──── SIM800L RX
            RX  ←──────────────────── SIM800L TX
            GND ────────────────────── GND rail

     SIM800L module
            A0 ──── Antenna (or wire antenna)
            Boot ── 4.0 V (usually tied to VCC)
```

## Code Example

```php
use Femus\Gsm\GsmModem;

$modem = GsmModem::open('/dev/cu.usbserial-XXXX', 115200);
$modem->init();  // Enable text mode, disable echo, enable unsolicited notifications

// Check if registered on network
if (!$modem->isRegistered()) {
    echo "Not yet registered. Check SIM and antenna.\n";
    exit(1);
}

// Signal strength (0–31; 99 = no service)
$signal = $modem->signalQuality();
printf("Signal: %s\n", $signal ?? 'unknown');

// Send SMS
$modem->sendSms('+79161234567', 'Hello from femus!');
echo "SMS sent.\n";

// Receive SMS (blocking loop)
$modem->onSmsReceived(function ($sms) {
    printf("From: %s\n", $sms->from);
    printf("Text: %s\n", $sms->text);
});

$modem->run();  // event loop
```

See `examples/sms-send.php` for a complete standalone script.

## SMS API

### Sending

```php
$modem->sendSms(string $number, string $text): void
```

- `$number`: Phone number as string — must match `/^\+?\d{3,15}$/` (digits, optional leading +, 3–15 digits total).
- `$text`: Message body (ASCII or Unicode, up to 160 characters).
- Throws `InvalidArgumentException` if phone number format is invalid.
- Throws `AtException` if the modem rejects the send command.

### Receiving

```php
$modem->onSmsReceived(callable $listener): void
```

```php
$listener(Sms $sms): void {
    echo $sms->from;  // string
    echo $sms->text;  // string
}
```

Register a callback. When an SMS arrives, the modem sends an unsolicited `+CMTI` notification, and the callback is invoked with the parsed message. Requires `$loop->run()` (event loop) to be active.

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| "Modem rejected 'ATE0'" | Baud mismatch or wrong port | Try 9600, and double-check the port in GsmModem::open() |
| "Not registered on the network yet" | SIM not detected or network unavailable | Check SIM physically, try another location, verify antenna |
| SMS sends but never arrives | Wrong number format or low signal | Check phone format matches +XXXX convention; verify signal quality |
| Modem resets during send | Insufficient power | Increase power supply capacity (2 A+ buck converter or LiPo); reduce TX power if modem supports it |
| USB adapter not visible on macOS | Driver missing (CH340/CP2102 etc.) | Install appropriate driver (e.g., https://github.com/WCHSoftware/ch340_ch341_linux) |

## Testing Checklist

1. Power supply verified (4.0 V DC, 2 A capable, common GND to all)
2. Wiring double-checked: TX↔RX crossed, GND common
3. SIM card inserted (PIN disabled recommended)
4. Antenna connected or nearby (small wire antenna works)
5. USB-TTL adapter detected on the host
6. Run `php examples/sms-send.php /dev/cu.usbserial-XXXX +1234567890 "test"` — SMS arrives within 5 seconds
7. Reply to the SMS — `onSmsReceived` callback in demo script captures it

---

See also: `docs/hardware-runs.md` for real hardware test results.
