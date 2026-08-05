<?php

declare(strict_types=1);

use Femus\Cli\Application;

it('prints usage and exits 0 with no command', function () {
    $lines = [];
    $code = (new Application('/project'))->run(['femus'], static function (string $l) use (&$lines): void {
        $lines[] = $l;
    });
    expect($code)->toBe(0)
        ->and(implode("\n", $lines))->toContain('firmware:flash');
});

it('exits 2 for an unknown command', function () {
    $code = (new Application('/project'))->run(['femus', 'bogus'], static fn (string $l) => null);
    expect($code)->toBe(2);
});
