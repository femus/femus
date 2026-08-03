<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

final class I2cReply
{
    public function __construct(
        public readonly int $address,
        public readonly int $register,
        public readonly string $data,
    ) {
    }

    /** Разбирает payload sysex-фрейма; null, если это не I2C_REPLY. */
    public static function fromSysexPayload(string $payload): ?self
    {
        if ($payload === '' || ord($payload[0]) !== Firmata::I2C_REPLY || strlen($payload) < 5) {
            return null;
        }
        $address = ord($payload[1]) | (ord($payload[2]) << 7);
        $register = ord($payload[3]) | (ord($payload[4]) << 7);
        $data = '';
        for ($i = 5; $i + 1 < strlen($payload); $i += 2) {
            $data .= chr(ord($payload[$i]) | (ord($payload[$i + 1]) << 7));
        }

        return new self($address, $register, $data);
    }
}
