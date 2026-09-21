<?php

declare(strict_types=1);

use Femus\Gsm\AtResponse;
use Femus\Gsm\ModemProbe;

/**
 * Replies shaped exactly like a live SIMCOM A7670G's on 2026-09-21, so the probe is
 * tested against what a real modem says rather than what the standard promises. The
 * identifiers are invented — the real ones do not belong in a public repository.
 *
 * @param array<string, array{0: bool, 1: list<string>}> $overrides
 */
function probeWith(array $overrides = []): ModemProbe
{
    $canned = [
        'ATE0' => [true, []],
        'ATI' => [true, ['Manufacturer: SIMCOM INCORPORATED', 'Model: A7670G-LABE', 'Revision: V1.11.2']],
        'AT+CGSN' => [true, ['860000000000123']],
        'AT+CPIN?' => [true, ['+CPIN: READY']],
        'AT+CNUM' => [true, ['+CNUM: "","15550000199",129']],
        'AT+CEREG?' => [true, ['+CEREG: 0,1']],
        'AT+COPS?' => [true, ['+COPS: 0,2,"302610",7']],
        'AT+CGATT?' => [true, ['+CGATT: 1']],
        'AT+CSQ' => [true, ['+CSQ: 21,99']],
        'AT+CMGF=1' => [true, []],
        'AT+CSCS=?' => [true, ['+CSCS: ("IRA","GSM","UCS2")']],
        'AT+CPMS?' => [true, ['+CPMS: "SM",2,20,"SM",2,20,"SM",2,20']],
        'AT+CCID' => [false, []],
        'AT+CICCID' => [true, ['+ICCID: 89000000000000000042']],
    ];

    $replies = array_merge($canned, $overrides);

    return new ModemProbe(function (string $command) use ($replies): AtResponse {
        [$ok, $lines] = $replies[$command] ?? [false, []];

        return new AtResponse($ok, $lines);
    });
}

it('reports who the modem is and what network it sits on', function () {
    $report = implode("\n", probeWith()->run());

    expect($report)->toContain('SIMCOM INCORPORATED, A7670G-LABE, V1.11.2')
        ->and($report)->toContain('READY')
        ->and($report)->toContain('registered (home)')
        ->and($report)->toContain('operator 302610 on E-UTRAN (LTE)')
        ->and($report)->toContain('data attached')
        ->and($report)->toContain('21/31 (-71 dBm)')
        ->and($report)->toContain('text mode')
        ->and($report)->toContain('storage SM 2/20');
});

it('keeps identifiers out of the report', function () {
    $report = implode("\n", probeWith()->run());

    expect($report)->not->toContain('860000000000123')   // IMEI
        ->and($report)->not->toContain('15550000199')     // the SIM's own number
        ->and($report)->not->toContain('89000000000000000042')
        ->and($report)->toContain('IMEI …0123')
        ->and($report)->toContain('number …0199');
});

it('notes the AT+CCID quirk this firmware has', function () {
    expect(implode("\n", probeWith()->run()))
        ->toContain('AT+CCID not supported — use AT+CICCID');
});

it('warns when SMS storage is about to overflow', function () {
    $report = implode("\n", probeWith(['AT+CPMS?' => [true, ['+CPMS: "SM",20,20,"SM",20,20,"SM",20,20']]])->run());

    expect($report)->toContain('nearly full')->toContain('AT+CMGD=1,4');
});

it('warns when the modem cannot do UCS2 at all', function () {
    $report = implode("\n", probeWith(['AT+CSCS=?' => [true, ['+CSCS: ("IRA","GSM")']]])->run());

    expect($report)->toContain('no UCS2 charset');
});

it('spells out a denied registration instead of a number', function () {
    $report = implode("\n", probeWith(['AT+CEREG?' => [true, ['+CEREG: 0,3']]])->run());

    expect($report)->toContain('registration DENIED');
});

it('says the antenna may be missing on a weak signal', function () {
    $weak = implode("\n", probeWith(['AT+CSQ' => [true, ['+CSQ: 4,99']]])->run());
    $none = implode("\n", probeWith(['AT+CSQ' => [true, ['+CSQ: 99,99']]])->run());

    expect($weak)->toContain('weak, check the antenna')
        ->and($none)->toContain('no service, or no antenna');
});

it('survives a modem that answers nothing useful', function () {
    $report = implode("\n", (new ModemProbe(fn () => new AtResponse(false, [])))->run());

    expect($report)->toContain('did not answer ATI')
        ->and($report)->toContain('PDU only');
});
