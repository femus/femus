<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Adapter\Firmata\BoardException;
use Femus\Cli\Command\FmRadio;
use Femus\Runtime\StreamSelectLoop;

/** @return array{0: int, 1: string} */
function fmRun(callable $call): array
{
    $lines = [];
    $code = $call(function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    return [$code, implode("\n", $lines)];
}

it('scan lists stations and ends tuned to the strongest', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->fakeI2c()->queueRead(teaStatus(87.5, 15));   // junk first reading, dropped
    for ($i = 0; $i <= 205; $i++) {
        $mhz = round(87.5 + $i * 0.1, 1);
        $level = match ($mhz) { 95.7 => 9, 101.3 => 13, default => 3 };
        $board->fakeI2c()->queueRead(teaStatus($mhz, $level, stereo: $level > 10));
    }
    $radio = new FmRadio(fn () => $board->tea5767(), settle: 0);

    [$code, $output] = fmRun(fn ($out) => $radio->scan(null, null, $out));

    expect($code)->toBe(0)
        ->and($output)->toContain(' 95.7 MHz')
        ->and($output)->toContain('101.3 MHz')
        ->and($output)->toContain('Noise floor 3/15, counting stations from 6.')
        ->and($output)->toContain('2 stations. Tuned to 101.3 MHz')
        ->and(end($board->fakeI2c()->writes)[1])->toBe("\x30\x69\x10\x12\x40");   // unmuted, on 101.3
});

it('scan with nothing above the threshold points at the antenna', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    for ($i = 0; $i <= 206; $i++) {
        $board->fakeI2c()->queueRead(teaStatus(87.5 + min($i, 205) * 0.1, 7));
    }

    [$code, $output] = fmRun(fn ($out) => (new FmRadio(fn () => $board->tea5767(), settle: 0))->scan(null, null, $out));

    expect($code)->toBe(1)->and($output)->toContain('antenna');
});

it('a silent module turns into a wiring checklist', function () {
    $board = new FakeBoard(new StreamSelectLoop());   // nothing queued: the read times out

    [$code, $output] = fmRun(fn ($out) => (new FmRadio(fn () => $board->tea5767(), settle: 0))->scan(null, null, $out));

    expect($code)->toBe(1)->and($output)->toContain('SDA to A4');
});

it('no board at all says so', function () {
    $radio = new FmRadio(fn () => throw new BoardException('No Firmata board found.'));

    [$code, $output] = fmRun(fn ($out) => $radio->tune(null, 101.3, $out));

    expect($code)->toBe(1)->and($output)->toContain('No Firmata board found.');
});

it('tune reports what the chip locked onto', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->fakeI2c()->queueRead(teaStatus(101.3, 12, stereo: true));

    [$code, $output] = fmRun(fn ($out) => (new FmRadio(fn () => $board->tea5767()))->tune(null, 101.3, $out));

    expect($code)->toBe(0)->and($output)->toBe('Tuned to 101.3 MHz: level 12/15, stereo.');
});
