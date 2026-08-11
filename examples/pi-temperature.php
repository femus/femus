<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// DS18B20 on a Raspberry Pi 1-Wire bus.
// Enable it once: add `dtoverlay=w1-gpio` to /boot/firmware/config.txt and reboot.
// Wiring: DATA -> GPIO4, VCC -> 3V3, GND -> GND, 4.7k pull-up between DATA and VCC.
//
// Usage: php examples/pi-temperature.php   (run on the Pi)
$board = Board::linux();

$sensors = $board->oneWireDevices();
if ($sensors === []) {
    echo "No DS18B20 found. Is dtoverlay=w1-gpio enabled and the sensor wired?\n";
    exit(1);
}

printf("Found %d sensor(s): %s\n", count($sensors), implode(', ', $sensors));

$thermometer = $board->ds18b20();   // first sensor on the bus

while (true) {
    $c = $thermometer->celsius();
    if ($c === null) {
        echo "Bad reading (CRC failed), retrying...\n";
    } else {
        printf("%.2f °C  /  %.2f °F\n", $c, $thermometer->fahrenheit());
    }
    sleep(2);
}
