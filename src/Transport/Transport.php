<?php

declare(strict_types=1);

namespace Femus\Transport;

interface Transport
{
    public function write(string $bytes): void;

    /** @return resource stream to register in the event loop */
    public function stream();

    /** Read all available bytes without blocking. */
    public function readAvailable(): string;

    public function close(): void;
}
