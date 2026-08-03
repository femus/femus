<?php

it('автозагрузка пакета работает', function () {
    expect(class_exists(\Composer\Autoload\ClassLoader::class))->toBeTrue();
});
