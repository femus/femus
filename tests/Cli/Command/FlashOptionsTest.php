<?php

declare(strict_types=1);

use Femus\Cli\Command\FlashOptions;

it('parses a target with options', function () {
    $parsed = FlashOptions::parse(['radio-bridge', '--port=/dev/cu.usbserial-1420', '--fqbn=arduino:avr:nano']);
    expect($parsed->target)->toBe('radio-bridge')
        ->and($parsed->options)->toBe([
            'port' => '/dev/cu.usbserial-1420',
            'fqbn' => 'arduino:avr:nano',
        ]);
});

it('leaves target null and options empty when absent', function () {
    $parsed = FlashOptions::parse([]);
    expect($parsed->target)->toBeNull()
        ->and($parsed->options)->toBe([]);
});

it('accepts options before the target', function () {
    $parsed = FlashOptions::parse(['--port=auto', 'femus']);
    expect($parsed->target)->toBe('femus')
        ->and($parsed->options)->toBe(['port' => 'auto']);
});
