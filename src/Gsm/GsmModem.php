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
        if (preg_match('/^\+?\d{3,15}$/', $number) !== 1) {
            throw new \InvalidArgumentException("Invalid phone number '{$number}' — expected digits with an optional leading +");
        }

        // Non-ASCII (Cyrillic, emoji…) only fits an SMS as UCS-2. The modem rejects a
        // UCS-2 body with "Invalid text mode parameter" unless its own character set is
        // switched too — and in that mode the *number* is hex as well. Verified on an
        // A7670G: CSCS=GSM + DCS 8 fails, this sequence sends.
        $ucs2 = Ucs2::isNeeded($text);
        $recipient = $number;

        if ($ucs2) {
            $this->channel->send('AT+CSCS="UCS2"');
            $this->channel->send('AT+CSMP=17,167,0,8');
            $recipient = Ucs2::encode($number);
        } else {
            $this->channel->send('AT+CSMP=17,167,0,0');
        }

        // One message holds 160 GSM characters, or only 70 as UCS-2 — past that the
        // modem answers "SMS size more than expected", so long text goes as several.
        $chunks = $ucs2 ? mb_str_split($text, 70) : str_split($text, 160);

        try {
            foreach ($chunks as $chunk) {
                $body = $ucs2 ? Ucs2::encode($chunk) : $chunk;
                if (!$this->channel->sendExpectingPrompt(sprintf('AT+CMGS="%s"', $recipient), $body)->ok) {
                    throw new AtException("Modem failed to send the SMS to {$number}");
                }
            }
        } finally {
            if ($ucs2) {
                // back to the character set the read path expects
                $this->channel->send('AT+CSCS="GSM"');
            }
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

        // only body lines reach pendingLines — OK/ERROR terminals are consumed by the channel
        $body = implode("\n", array_slice($response->lines, 1));

        return new Sms(Ucs2::decode($m[1]), Ucs2::decode($body));
    }

    public function deleteSms(int $index): void
    {
        // best-effort: modem errors are intentionally ignored
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

    /** Blocks processing incoming notifications (SMS etc.) until interrupted. */
    public function run(): void
    {
        $this->channel->run();
    }
}
