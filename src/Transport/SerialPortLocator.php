<?php

declare(strict_types=1);

namespace Femus\Transport;

use Sanchescom\Serial\SerialPortLocator as Locator;

/** Lists serial ports that may hold a board; delegates to sanchescom/php-serial. */
final class SerialPortLocator
{
    private Locator $locator;

    /** @param \Closure(string): list<string>|null $glob */
    public function __construct(?\Closure $glob = null)
    {
        $this->locator = new Locator($glob);
    }

    /** @return list<string> */
    public function candidates(): array
    {
        return $this->locator->candidates();
    }
}
