<?php

declare(strict_types=1);

namespace Femus\Adapter\Fake;

use Femus\Contracts\RadioLink;
use Femus\Contracts\RadioMessage;

final class FakeRadioLink implements RadioLink
{
    /** @var list<array{0: int, 1: string}> */
    public array $sent = [];

    /** @var list<callable> */
    private array $listeners = [];

    public function __construct(private readonly int $address)
    {
    }

    public function send(int $toAddress, string $message): void
    {
        $this->validateAddress($toAddress);
        $this->validateMessage($message);

        $this->sent[] = [$toAddress, $message];
    }

    public function onMessage(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function address(): int
    {
        return $this->address;
    }

    /** Test input: inject an incoming radio message. */
    public function simulateMessage(int $from, int $to, string $message): void
    {
        $radioMessage = new RadioMessage($from, $to, $message);
        foreach ($this->listeners as $listener) {
            $listener($radioMessage);
        }
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
