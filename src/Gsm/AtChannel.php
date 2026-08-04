<?php

declare(strict_types=1);

namespace Femus\Gsm;

use Femus\Runtime\Loop;
use Femus\Transport\Transport;

/**
 * Unsolicited listeners may issue their own send() calls: they run only while the channel is idle.
 */
final class AtChannel
{
    private string $buffer = '';

    private ?string $pendingCommand = null;

    /** @var list<string> */
    private array $pendingLines = [];

    private ?bool $pendingOk = null;

    /** @var list<string> */
    private array $queuedUnsolicited = [];

    /** @var list<callable> */
    private array $unsolicitedListeners = [];

    public function __construct(
        private readonly Transport $transport,
        private readonly Loop $loop,
        private readonly float $commandTimeout = 5.0,
    ) {
        $loop->addReadStream(
            $transport->stream(),
            fn () => $this->ingest($transport->readAvailable()),
        );
    }

    public function onUnsolicited(callable $listener): void
    {
        $this->unsolicitedListeners[] = $listener;
    }

    public function isBusy(): bool
    {
        return $this->pendingCommand !== null;
    }

    public function send(string $command): AtResponse
    {
        $this->pendingCommand = $command;
        $this->pendingLines = [];
        $this->pendingOk = null;
        $this->transport->write($command . "\r");

        $deadline = hrtime(true) / 1e9 + $this->commandTimeout;
        while ($this->pendingOk === null) {
            $remaining = $deadline - hrtime(true) / 1e9;
            if ($remaining <= 0) {
                $this->pendingCommand = null;
                $this->flushQueuedUnsolicited();
                throw new AtException(sprintf(
                    "Modem did not respond to '%s' within %.1fs (is it powered and on the right port?)",
                    $command,
                    $this->commandTimeout,
                ));
            }
            $this->loop->tick(min(0.05, $remaining));
        }

        $response = new AtResponse($this->pendingOk, $this->pendingLines);
        $this->pendingCommand = null;
        $this->flushQueuedUnsolicited();

        return $response;
    }

    private function ingest(string $chunk): void
    {
        $this->buffer .= $chunk;
        while (($pos = strpos($this->buffer, "\n")) !== false) {
            $line = rtrim(substr($this->buffer, 0, $pos), "\r");
            $this->buffer = substr($this->buffer, $pos + 1);
            if ($line === '') {
                continue;
            }
            $this->handleLine($line);
        }
    }

    private function handleLine(string $line): void
    {
        if ($this->pendingCommand === null) {
            $this->dispatchUnsolicited($line);

            return;
        }
        if ($line === trim($this->pendingCommand)) {
            return; // command echo
        }
        if ($line === 'OK') {
            $this->pendingOk = true;

            return;
        }
        if ($line === 'ERROR' || str_starts_with($line, '+CME ERROR') || str_starts_with($line, '+CMS ERROR')) {
            $this->pendingLines[] = $line;
            $this->pendingOk = false;

            return;
        }
        if (str_starts_with($line, '+CMTI:')) {
            $this->queuedUnsolicited[] = $line; // event, not part of the response

            return;
        }
        $this->pendingLines[] = $line;
    }

    private function flushQueuedUnsolicited(): void
    {
        while ($this->queuedUnsolicited !== []) {
            $this->dispatchUnsolicited(array_shift($this->queuedUnsolicited));
        }
    }

    private function dispatchUnsolicited(string $line): void
    {
        foreach ($this->unsolicitedListeners as $listener) {
            $listener($line);
        }
    }
}
