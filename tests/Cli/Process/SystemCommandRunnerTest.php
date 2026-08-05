<?php

declare(strict_types=1);

use Femus\Cli\Process\SystemCommandRunner;

it('runs a command, captures output and a zero exit code', function () {
    $result = (new SystemCommandRunner())->run(['printf', 'hello']);
    expect($result->succeeded())->toBeTrue()
        ->and($result->output)->toContain('hello');
});

it('reports a non-zero exit code for a failing command', function () {
    $result = (new SystemCommandRunner())->run(['false']);
    expect($result->succeeded())->toBeFalse();
});
