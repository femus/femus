<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

final class RadioMessageFrame
{
    public function __construct(
        public readonly int $from,
        public readonly int $to,
        public readonly string $message,
    ) {
    }

    /** Parses a sysex payload; null when it is not a radio recv frame. */
    public static function fromSysexPayload(string $payload): ?self
    {
        if (strlen($payload) < 4
            || ord($payload[0]) !== Firmata::FEMUS_RADIO
            || ord($payload[1]) !== Firmata::RADIO_RECV) {
            return null;
        }

        $from = ord($payload[2]);
        $to = ord($payload[3]);
        $message = '';

        for ($i = 4; $i + 1 < strlen($payload); $i += 2) {
            $message .= chr((ord($payload[$i]) & 0x7F) | ((ord($payload[$i + 1]) & 0x01) << 7));
        }

        return new self($from, $to, $message);
    }
}
