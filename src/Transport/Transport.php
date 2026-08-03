<?php

declare(strict_types=1);

namespace Femus\Transport;

interface Transport
{
    public function write(string $bytes): void;

    /** @return resource поток для регистрации в event loop */
    public function stream();

    /** Прочитать всё, что накопилось, без блокировки. */
    public function readAvailable(): string;

    public function close(): void;
}
