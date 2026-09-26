<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

use Femus\Contracts\I2cBus;
use Femus\Contracts\I2cException;
use Femus\Runtime\Loop;
use Femus\Transport\Transport;

final class FirmataI2cBus implements I2cBus
{
    /**
     * Replies in arrival order. A list, not one slot: a request that waits spins the loop,
     * and a second request started from inside that spin must not wipe out the first's reply.
     *
     * @var list<I2cReply>
     */
    private array $replies = [];

    public function __construct(
        private readonly Transport $transport,
        FirmataParser $parser,
        private readonly Loop $loop,
        private readonly float $timeout = 1.0,
    ) {
        $parser->onSysex(function (string $payload): void {
            $reply = I2cReply::fromSysexPayload($payload);
            if ($reply !== null) {
                $this->replies[] = $reply;
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
        return $this->request(
            FirmataEncoder::i2cReadRegister($address, $register, $length),
            $address,
            $register,
            sprintf('I2C device 0x%02X did not reply for register 0x%02X (is it connected? correct address?)', $address, $register),
        );
    }

    public function read(int $address, int $length): string
    {
        // the firmware answers a register-less read with register 0
        return $this->request(
            FirmataEncoder::i2cRead($address, $length),
            $address,
            0,
            sprintf('I2C device 0x%02X did not reply (is it connected? correct address?)', $address),
        );
    }

    private function request(string $frame, int $address, int $register, string $timeoutMessage): string
    {
        $this->transport->write($frame);

        $deadline = hrtime(true) / 1e9 + $this->timeout;
        while (true) {
            // ponytail: a reply that outlived its timed-out request answers the next one to the
            // same device and register; stale by one read, never another device's data
            foreach ($this->replies as $i => $reply) {
                if ($reply->address === $address && $reply->register === $register) {
                    array_splice($this->replies, $i, 1);

                    return $reply->data;
                }
            }
            $remaining = $deadline - hrtime(true) / 1e9;
            if ($remaining <= 0) {
                throw new I2cException($timeoutMessage);
            }
            $this->loop->tick(min(0.05, $remaining));
        }
    }
}
