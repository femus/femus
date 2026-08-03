<?php

declare(strict_types=1);

it('автозагрузка пакета работает', function () {
    expect(class_exists(\Composer\Autoload\ClassLoader::class))->toBeTrue();
});
