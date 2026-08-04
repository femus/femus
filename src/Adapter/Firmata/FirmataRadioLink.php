<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

use Femus\Contracts\RadioLink;
use Femus\Contracts\RadioMessage;
use Femus\Transport\Transport;

/**
 * Radio link over the femus sysex extension.
 * v1 supports one radio per board: the protocol carries no channel id,
 * so every message frame is delivered to every FirmataRadioLink.
 */
final class FirmataRadioLink implements RadioLink
{
    /** @var list<callable> */
    private array $listeners = [];

    public function __construct(
        private readonly Transport $transport,
        FirmataParser $parser,
        private readonly int $address,
        int $rxPin,
        int $txPin,
    ) {
        $parser->onSysex(function (string $payload): void {
            $frame = RadioMessageFrame::fromSysexPayload($payload);
            if ($frame === null) {
                return;
            }
            $message = new RadioMessage($frame->from, $frame->to, $frame->message);
            foreach ($this->listeners as $listener) {
                $listener($message);
            }
        });
        $transport->write(FirmataEncoder::radioAttach($address, $rxPin, $txPin));
    }

    public function send(int $toAddress, string $message): void
    {
        $this->validateAddress($toAddress);
        $this->validateMessage($message);

        $this->transport->write(FirmataEncoder::radioSend($toAddress, $message));
    }

    public function onMessage(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function address(): int
    {
        return $this->address;
    }

    private function validateAddress(int $toAddress): void
    {
        if ($toAddress < 0 || $toAddress > 127) {
            throw new \InvalidArgumentException('Radio address must be 0-127');
        }
    }

    private function validateMessage(string $message): void
    {
        if ($message === '') {
            throw new \InvalidArgumentException('Radio message is limited to 50 bytes');
        }
        if (strlen($message) > 50) {
            throw new \InvalidArgumentException('Radio message is limited to 50 bytes');
        }
    }
}
