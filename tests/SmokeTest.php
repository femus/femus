<?php

declare(strict_types=1);

it('package autoloading works', function () {
    expect(class_exists(\Composer\Autoload\ClassLoader::class))->toBeTrue();
});
