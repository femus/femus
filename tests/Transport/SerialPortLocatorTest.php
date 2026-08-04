<?php

declare(strict_types=1);

use Femus\Transport\SerialPortLocator;

it('lists usb serial candidates on this OS', function () {
    $fakeGlob = function (string $pattern): array {
        return match (true) {
            str_contains($pattern, 'usbserial') => ['/dev/cu.usbserial-1420'],
            str_contains($pattern, 'ttyUSB') => ['/dev/ttyUSB0'],
            default => [],
        };
    };
    $locator = new SerialPortLocator($fakeGlob(...));
    $expected = PHP_OS_FAMILY === 'Darwin' ? '/dev/cu.usbserial-1420' : '/dev/ttyUSB0';
    expect($locator->candidates())->toContain($expected);
});

it('excludes noise ports and deduplicates', function () {
    $fakeGlob = fn (string $pattern): array => str_contains($pattern, 'cu.*') || str_contains($pattern, 'usbserial')
        ? ['/dev/cu.Bluetooth-Incoming-Port', '/dev/cu.debug-console', '/dev/cu.usbserial-1420', '/dev/cu.HC-05-DevB', '/dev/cu.usbserial-1420']
        : [];
    $locator = new SerialPortLocator($fakeGlob(...));
    $candidates = $locator->candidates();
    expect($candidates)->not->toContain('/dev/cu.Bluetooth-Incoming-Port')
        ->and($candidates)->not->toContain('/dev/cu.debug-console');
    if (PHP_OS_FAMILY === 'Darwin') {
        expect($candidates)->toContain('/dev/cu.HC-05-DevB')
            ->and(array_count_values($candidates)['/dev/cu.usbserial-1420'])->toBe(1);
    }
});
