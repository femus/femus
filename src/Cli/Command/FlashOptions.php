<?php

declare(strict_types=1);

namespace Femus\Cli\Command;

final class FlashOptions
{
    /** @param array<string, string> $options */
    public function __construct(
        public readonly ?string $target,
        public readonly array $options,
    ) {
    }

    /** @param list<string> $args */
    public static function parse(array $args): self
    {
        $target = null;
        $options = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, '');
                $options[$key] = $value;
            } elseif ($target === null) {
                $target = $arg;
            }
        }

        return new self($target, $options);
    }
}
