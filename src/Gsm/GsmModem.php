<?php

declare(strict_types=1);

namespace Femus\Gsm;

use Femus\Runtime\Loop;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\SerialPort;

final class GsmModem
{
    public function __construct(private readonly AtChannel $channel)
    {
    }

    public static function open(string $device, int $baudRate = 115200, ?Loop $loop = null): self
    {
        return new self(new AtChannel(new SerialPort($device, $baudRate), $loop ?? new StreamSelectLoop()));
    }

    public function init(): void
    {
        foreach (['ATE0', 'AT+CMGF=1', 'AT+CNMI=2,1,0,0,0'] as $command) {
            if (!$this->channel->send($command)->ok) {
                throw new AtException("Modem rejected '{$command}'");
            }
        }
    }

    public function isRegistered(): bool
    {
        $line = $this->channel->send('AT+CREG?')->firstLine() ?? '';
        if (preg_match('/\+CREG: \d+,(\d+)/', $line, $m) !== 1) {
            return false;
        }

        return in_array((int) $m[1], [1, 5], true);
    }

    public function signalQuality(): ?int
    {
        $line = $this->channel->send('AT+CSQ')->firstLine() ?? '';
        if (preg_match('/\+CSQ: (\d+),/', $line, $m) !== 1) {
            return null;
        }
        $rssi = (int) $m[1];

        return $rssi === 99 ? null : $rssi;
    }

    public function sendSms(string $number, string $text): void
    {
        $response = $this->channel->sendExpectingPrompt(sprintf('AT+CMGS="%s"', $number), $text);
        if (!$response->ok) {
            throw new AtException("Modem failed to send the SMS to {$number}");
        }
    }

    public function readSms(int $index): Sms
    {
        $response = $this->channel->send('AT+CMGR=' . $index);
        $header = $response->firstLine();
        if (!$response->ok || $header === null
            || preg_match('/\+CMGR: "[^"]*","([^"]*)"/', $header, $m) !== 1) {
            throw new AtException("Failed to read SMS at index {$index}");
        }

        return new Sms($m[1], implode("\n", array_slice($response->lines, 1)));
    }

    public function deleteSms(int $index): void
    {
        $this->channel->send('AT+CMGD=' . $index);
    }

    /** @param callable(Sms): void $listener */
    public function onSmsReceived(callable $listener): void
    {
        $this->channel->onUnsolicited(function (string $line) use ($listener): void {
            if (preg_match('/\+CMTI: "[^"]*",(\d+)/', $line, $m) !== 1) {
                return;
            }
            $listener($this->readSms((int) $m[1]));
        });
    }
}
