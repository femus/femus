# GSM & SMS

`Femus\Gsm` is an AT-command stack for GSM modems (tested with the SIM800L module):
send and receive SMS from PHP over any serial transport.

```php
use Femus\Gsm\GsmModem;

$modem = GsmModem::open('/dev/cu.usbserial-1420');

$modem->init();
if (!$modem->isRegistered()) {
    exit("no network\n");
}

$modem->sendSms('+15551234567', 'Hello from PHP');
```

Receiving works through the event loop:

```php
$modem->onSmsReceived(function ($sms) {
    printf("from %s: %s\n", $sms->from, $sms->text);
});

$modem->run();
```

Also available: `signalQuality()`, `readSms()`, `deleteSms()`.

::: warning SIM800L power
The SIM800L needs 3.4–4.2 V and bursts up to 2 A during transmission — do **not**
power it from the Arduino 5V pin. Use a dedicated supply (or a Li-ion cell) with
a common ground, and connect the antenna before powering up.
:::

See `examples/sms-send.php` for a complete script.

## Meeting a new modem: `femus modem:probe`

Any AT modem works in principle; every module still has its own quirks — logic voltage,
baud rate, which command returns the ICCID. Instead of an evening of one-off scripts,
ask it:

```bash
php bin/femus modem:probe /dev/cu.usbserial-130
```

```
Port:     /dev/cu.usbserial-130 @ 115200 baud
Modem:    SIMCOM INCORPORATED, A7670G-LABE, V1.11.2, IMEI …6178
SIM:      READY, number …5158
Network:  registered (home), operator 302610 on E-UTRAN (LTE), data attached
Signal:   21/31 (-71 dBm)
SMS:      text mode, charsets IRA,GSM,UCS2, storage SM 2/20
ICCID:    …9012
Quirk:    AT+CCID not supported — use AT+CICCID for the ICCID
```

It walks the common baud rates, stops at the one that answers, and prints a block you
paste straight into `docs/hardware-runs.md`. IMEI, ICCID and the SIM's own number are
cut to their last digits, because that block ends up in a public repository.

Silence at *every* baud rate is a finding of its own: a wrong baud still returns garbage
bytes, so nothing at all means the link is broken — and the command prints the checklist
(power, shared GND, the level converter's LV pin, TX/RX crossed) in the order worth
checking.
