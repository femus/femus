<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Gsm\GsmModem;

// Usage: php examples/sms-send.php /dev/cu.usbserial-XXXX +79161234567 "Hello from femus"
[$script, $port, $number, $text] = $argv + [null, null, null, 'Hello from femus'];
if ($port === null || $number === null) {
    exit("Usage: php examples/sms-send.php <serial-port> <number> [text]\n");
}

$modem = GsmModem::open($port);
$modem->init();

if (!$modem->isRegistered()) {
    exit("Not registered on the network yet — check the SIM and antenna, then retry.\n");
}

printf("Signal: %s\n", $modem->signalQuality() ?? 'unknown');
$modem->sendSms($number, $text);
echo "SMS sent.\n";
