<?php

declare(strict_types=1);

namespace Femus\Device;

use Femus\Contracts\I2cBus;

/**
 * MPU-6050 gyroscope/accelerometer (GY-521 module).
 */
final class Mpu6050
{
    private const REG_PWR_MGMT_1 = 0x6B;
    private const REG_ACCEL = 0x3B;
    private const REG_TEMP = 0x41;
    private const REG_GYRO = 0x43;

    public function __construct(
        private readonly I2cBus $bus,
        private readonly int $address = 0x68,
    ) {
        $bus->write($address, chr(self::REG_PWR_MGMT_1) . "\x00"); // wake from sleep
    }

    /**
     * @return array{x: float, y: float, z: float} acceleration in g (±2g range)
     */
    public function readAccel(): array
    {
        [$x, $y, $z] = $this->readThreeInt16(self::REG_ACCEL);

        return ['x' => $x / 16384.0, 'y' => $y / 16384.0, 'z' => $z / 16384.0];
    }

    /**
     * @return array{x: float, y: float, z: float} angular velocity in °/s (±250 range)
     */
    public function readGyro(): array
    {
        [$x, $y, $z] = $this->readThreeInt16(self::REG_GYRO);

        return ['x' => $x / 131.0, 'y' => $y / 131.0, 'z' => $z / 131.0];
    }

    public function readTemperature(): float
    {
        $raw = $this->readRegister(self::REG_TEMP, 2);

        return $this->int16($raw, 0) / 340.0 + 36.53;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function readThreeInt16(int $register): array
    {
        $raw = $this->readRegister($register, 6);

        return [$this->int16($raw, 0), $this->int16($raw, 2), $this->int16($raw, 4)];
    }

    private function readRegister(int $register, int $length): string
    {
        return $this->bus->readRegister($this->address, $register, $length);
    }

    private function int16(string $bytes, int $offset): int
    {
        $value = (ord($bytes[$offset]) << 8) | ord($bytes[$offset + 1]);

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }
}
