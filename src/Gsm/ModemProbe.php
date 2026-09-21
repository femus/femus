<?php

declare(strict_types=1);

namespace Femus\Gsm;

/**
 * Asks a modem who it is and what it can do, and prints the answer as a block you
 * paste into docs/hardware-runs.md. Meeting a new module used to mean an evening of
 * one-off scripts; this is that evening, written down once.
 *
 * Identifiers that end up in a public repository — IMEI, the SIM's own number — are
 * cut down to their last digits on the way out.
 */
final class ModemProbe
{
    /** +COPS access technology codes, the ones a modem on a live network reports. */
    private const RADIO = [
        0 => 'GSM', 1 => 'GSM Compact', 2 => 'UTRAN (3G)', 3 => 'GSM EDGE',
        4 => 'UTRAN HSDPA', 5 => 'UTRAN HSUPA', 6 => 'UTRAN HSPA',
        7 => 'E-UTRAN (LTE)', 8 => 'EC-GSM-IoT', 9 => 'E-UTRAN NB-S1',
        10 => 'E-UTRA 5GCN', 11 => 'NR 5GCN', 12 => 'NG-RAN (5G)', 13 => 'E-UTRA/NR',
    ];

    private const REGISTRATION = [
        0 => 'not registered, not searching',
        1 => 'registered (home)',
        2 => 'searching…',
        3 => 'registration DENIED',
        4 => 'unknown',
        5 => 'registered (roaming)',
        6 => 'registered for SMS only (home)',
        7 => 'registered for SMS only (roaming)',
        8 => 'emergency calls only',
        // seen on an A7670G with the SIM tray empty
        11 => 'emergency services only — no usable SIM',
    ];

    /** @param callable(string): AtResponse $send */
    public function __construct(private $send)
    {
    }

    /** @return list<string> report lines, ready to paste into a hardware run */
    public function run(): array
    {
        $report = [];
        $quirks = [];

        $report[] = 'Modem:    ' . $this->identity();

        $sim = $this->sim();
        $report[] = 'SIM:      ' . $sim;
        $report[] = 'Network:  ' . $this->network(str_contains($sim, 'no SIM'));
        $report[] = 'Signal:   ' . $this->signal();
        $report[] = 'SMS:      ' . $this->sms($quirks);

        $iccid = $this->iccid($quirks);
        if ($iccid !== null) {
            $report[] = 'ICCID:    ' . $iccid;
        }

        foreach ($quirks as $quirk) {
            $report[] = 'Quirk:    ' . $quirk;
        }

        return $report;
    }

    private function identity(): string
    {
        $response = $this->ask('ATI');
        if (!$response->ok) {
            return 'did not answer ATI';
        }

        $parts = [];
        foreach ($response->lines as $line) {
            $line = trim($line);
            // "Manufacturer: SIMCOM INCORPORATED", "Model: A7670G-LABE", "Revision: V1.11.2"
            if ($line === '' || preg_match('/^IMEI/i', $line) === 1) {
                continue;
            }
            $parts[] = preg_replace('/^(Manufacturer|Model|Revision):\s*/i', '', $line) ?? $line;
        }

        $imei = $this->ask('AT+CGSN')->firstLine() ?? '';
        if (preg_match('/\d{14,15}/', $imei, $m) === 1) {
            $parts[] = 'IMEI …' . substr($m[0], -4);
        }

        return $parts === [] ? 'unknown' : implode(', ', $parts);
    }

    private function sim(): string
    {
        $response = $this->ask('AT+CPIN?');
        $first = trim($response->firstLine() ?? '');

        if (!$response->ok) {
            // "+CME ERROR: SIM not inserted" — an empty tray, not a broken modem
            return stripos($first, 'not inserted') !== false || stripos($first, 'not present') !== false
                ? 'no SIM inserted'
                : ($first === '' ? 'no answer' : $first);
        }

        $state = preg_match('/\+CPIN:\s*(.+)$/', $first, $m) === 1 ? trim($m[1]) : 'no answer';

        $number = $this->ask('AT+CNUM')->firstLine() ?? '';
        if (preg_match('/"(\+?\d{6,15})"/', $number, $m) === 1) {
            $state .= ', number …' . substr($m[1], -4);
        }

        return $state;
    }

    private function network(bool $simMissing = false): string
    {
        if ($simMissing) {
            return 'nothing to register with until a SIM is in';
        }

        // LTE registration lives in +CEREG; +CREG alone can read 0 on a perfectly attached modem
        $state = null;
        foreach (['AT+CEREG?', 'AT+CREG?'] as $command) {
            $line = $this->ask($command)->firstLine() ?? '';
            if (preg_match('/\+C(?:E)?REG:\s*\d+,(\d+)/', $line, $m) === 1) {
                $state = self::REGISTRATION[(int) $m[1]] ?? "state {$m[1]}";
                break;
            }
        }

        $operator = $this->ask('AT+COPS?')->firstLine() ?? '';
        if (preg_match('/\+COPS:\s*\d+,\d+,"([^"]+)"(?:,(\d+))?/', $operator, $m) === 1) {
            $radio = isset($m[2]) ? (self::RADIO[(int) $m[2]] ?? "AcT {$m[2]}") : 'unknown radio';
            $state = ($state ?? 'unknown') . sprintf(', operator %s on %s', $m[1], $radio);
        }

        $attached = $this->ask('AT+CGATT?')->firstLine() ?? '';
        if (preg_match('/\+CGATT:\s*(\d)/', $attached, $m) === 1) {
            $state .= $m[1] === '1' ? ', data attached' : ', no data attach';
        }

        return $state ?? 'no answer';
    }

    private function signal(): string
    {
        $line = $this->ask('AT+CSQ')->firstLine() ?? '';
        if (preg_match('/\+CSQ:\s*(\d+)/', $line, $m) !== 1) {
            return 'no answer';
        }

        $rssi = (int) $m[1];
        if ($rssi === 99) {
            return 'unknown (99) — no service, or no antenna';
        }

        // 3GPP 27.007: 0 = -113 dBm, each step 2 dB
        return sprintf('%d/31 (%d dBm)%s', $rssi, -113 + 2 * $rssi, $rssi < 10 ? ' — weak, check the antenna' : '');
    }

    /** @param list<string> $quirks */
    private function sms(array &$quirks): string
    {
        $parts = [];
        $parts[] = $this->ask('AT+CMGF=1')->ok ? 'text mode' : 'NO text mode (PDU only — femus needs text mode)';

        $charsets = $this->ask('AT+CSCS=?')->firstLine() ?? '';
        if (preg_match('/\+CSCS:\s*\((.+)\)/', $charsets, $m) === 1) {
            $list = str_replace('"', '', $m[1]);
            $parts[] = "charsets {$list}";
            if (!str_contains(strtoupper($list), 'UCS2')) {
                $quirks[] = 'no UCS2 charset — non-Latin text (Cyrillic, emoji) cannot be sent';
            }
        }

        $storageResponse = $this->ask('AT+CPMS?');
        $storage = $storageResponse->firstLine() ?? '';
        if (!$storageResponse->ok) {
            $parts[] = 'storage unreadable (no SIM?)';
        }
        if (preg_match('/\+CPMS:\s*"(\w+)",(\d+),(\d+)/', $storage, $m) === 1) {
            $parts[] = sprintf('storage %s %d/%d', $m[1], (int) $m[2], (int) $m[3]);
            if ((int) $m[3] > 0 && (int) $m[2] >= (int) $m[3] - 2) {
                $quirks[] = 'SMS storage nearly full — incoming messages will be lost: AT+CMGD=1,4';
            }
        }

        return implode(', ', $parts);
    }

    /** @param list<string> $quirks */
    private function iccid(array &$quirks): ?string
    {
        foreach (['AT+CCID', 'AT+CICCID'] as $command) {
            $response = $this->ask($command);
            if ($response->ok && preg_match('/(\d{15,20})/', implode(' ', $response->lines), $m) === 1) {
                if ($command === 'AT+CICCID') {
                    $quirks[] = 'AT+CCID not supported — use AT+CICCID for the ICCID';
                }

                return '…' . substr($m[1], -4);
            }
        }

        return null;
    }

    private function ask(string $command): AtResponse
    {
        return ($this->send)($command);
    }
}
