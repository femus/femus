<?php

declare(strict_types=1);

namespace Femus\Contracts;

/**
 * Reads Dallas 1-Wire devices exposed by the Linux kernel's w1 subsystem
 * (Raspberry Pi and friends). Abstracted so devices can be driven from a fake
 * in tests without any hardware or sysfs.
 */
interface OneWireReader
{
    /**
     * Device ids currently present on the bus (e.g. "28-0000075c2d3f").
     *
     * @return list<string>
     */
    public function devices(): array;

    /** Raw contents of a device's w1_slave file. */
    public function read(string $deviceId): string;
}
