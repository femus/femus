<?php

declare(strict_types=1);

use Femus\Gsm\Gateway\Command\HelpCommand;
use Femus\Gsm\Gateway\Command\PingCommand;
use Femus\Gsm\Sms;

it('ping replies pong', function () {
    expect((new PingCommand())->handle('', new Sms('+1', '/ping')))->toBe('pong');
});

it('help lists the registered commands', function () {
    $help = (new HelpCommand())->withCommands([new PingCommand()]);
    $text = $help->handle('', new Sms('+1', '/help'));

    expect($text)->toContain('/ping — check the gateway is alive');
});
