<?php

declare(strict_types=1);

use Femus\Gsm\Ucs2;

it('decodes a Cyrillic message that arrived as UCS-2 hex', function () {
    // the real message from the bench run on 2026-09-19
    $hex = '04210442043E043B0438044604300020043D043E0432043E0439';
    expect(Ucs2::decode($hex))->toBe('Столица новой');
});

it('leaves plain text alone', function () {
    expect(Ucs2::decode('Hello from femus'))->toBe('Hello from femus')
        ->and(Ucs2::decode('/ping'))->toBe('/ping');
});

it('does not mistake an ASCII word of hex letters for UCS-2', function () {
    expect(Ucs2::decode('DEADBEEF'))->toBe('DEADBEEF')
        ->and(Ucs2::decode('FACE'))->toBe('FACE');
});

it('round-trips through encode', function () {
    foreach (['Привет, это femus', '你好', 'naïve café'] as $text) {
        expect(Ucs2::decode(Ucs2::encode($text)))->toBe($text);
    }
});

it('knows when UCS-2 is needed', function () {
    expect(Ucs2::isNeeded('plain ascii, 123'))->toBeFalse()
        ->and(Ucs2::isNeeded('Привет'))->toBeTrue()
        ->and(Ucs2::isNeeded('café'))->toBeTrue();
});
