<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

use Femus\Contracts\I2cBus;
use Femus\Contracts\I2cException;
use Femus\Runtime\Loop;
use Femus\Transport\Transport;

final class FirmataI2cBus implements I2cBus
{
    private ?I2cReply $pendingReply = null;

    public function __construct(
        private readonly Transport $transport,
        FirmataParser $parser,
        private readonly Loop $loop,
        private readonly float $timeout = 1.0,
    ) {
        $parser->onSysex(function (string $payload): void {
            $reply = I2cReply::fromSysexPayload($payload);
            if ($reply !== null) {
                $this->pendingReply = $reply;
            }
        });
        $transport->write(FirmataEncoder::i2cConfig());
    }

    public function write(int $address, string $bytes): void
    {
        $this->transport->write(FirmataEncoder::i2cWrite($address, $bytes));
    }

    public function readRegister(int $address, int $register, int $length): string
    {
        $this->pendingReply = null;
        $this->transport->write(FirmataEncoder::i2cReadRegister($address, $register, $length));

        $deadline = hrtime(true) / 1e9 + $this->timeout;
        while (true) {
            $reply = $this->pendingReply;
            if ($reply !== null && $reply->address === $address && $reply->register === $register) {
                return $reply->data;
            }
            $remaining = $deadline - hrtime(true) / 1e9;
            if ($remaining <= 0) {
                throw new I2cException(
                    sprintf(
                        'I2C device 0x%02X did not reply for register 0x%02X (is it connected? correct address?)',
                        $address,
                        $register,
                    ),
                );
            }
            $this->loop->tick(min(0.05, $remaining));
        }
    }
}
