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
