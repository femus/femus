<?php

declare(strict_types=1);

namespace Femus;

use Femus\Contracts\BoardInterface;
use Femus\Runtime\Loop;

abstract class AbstractBoard implements BoardInterface
{
    public function __construct(protected readonly Loop $loop)
    {
    }

    public function loop(): Loop
    {
        return $this->loop;
    }

    public function run(): void
    {
        $this->loop->run();
    }

    public function stop(): void
    {
        $this->loop->stop();
    }
}
