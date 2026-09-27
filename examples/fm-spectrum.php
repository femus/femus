<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Adapter\Firmata\BoardException;
use Femus\Adapter\Firmata\FirmataBoard;
use Femus\Board;
use Femus\Contracts\I2cException;
use Femus\Device\Tea5767;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\TransportException;

// Live FM band in the browser: the TEA5767 sweeps 87.5–108 MHz one step at a time and the
// page draws every level as it lands. Click a station to stop the sweep and listen to it.
// Same dependency-free HTTP server as radio-web-chat.php, on the femus event loop.
// Pull the USB cable and the page says so, then picks up where it was once it is back.
//
// Usage: php examples/fm-spectrum.php [port] [http-port]
//   then open http://localhost:8080
$port = $argv[1] ?? null;
$httpPort = (int) ($argv[2] ?? 8080);
$loop = new StreamSelectLoop();

$frequencies = [];
for ($f = Tea5767::MIN_MHZ; $f <= Tea5767::MAX_MHZ + 0.01; $f += 0.1) {
    $frequencies[] = round($f, 1);
}
/** @var list<?int> $levels null until the sweep first reaches that point */
$levels = array_fill(0, count($frequencies), null);
$cursor = 0;
$sweeps = 0;
$listening = null; // MHz while a station plays, null while sweeping
$error = null;     // what went wrong, shown on the page until the receiver is back

/** @var ?FirmataBoard $board */
$board = null;
/** @var ?Tea5767 $radio */
$radio = null;

$connect = function () use (&$board, &$radio, $port, $loop): void {
    $muted = $radio?->isMuted() ?? false;
    $board = Board::firmata($port, loop: $loop);
    $radio = $board->tea5767();
    $radio->mute($muted);
    // the first reading after power-up is junk — take it and drop it
    $radio->tune(Tea5767::MIN_MHZ, mute: true);
    usleep(50_000);
    $radio->status();
};

$step = null; // the sweep, defined below; recovery restarts it

$retry = function () use (&$retry, &$error, &$listening, &$radio, &$step, $connect, $loop): void {
    try {
        $connect();
    } catch (BoardException | I2cException | TransportException) {
        $loop->addTimer(2.0, $retry);

        return;
    }
    $error = null;
    printf("[%s] receiver is back\n", date('H:i:s'));
    $listening !== null ? $radio->tune($listening) : $step();
};

$fail = function (\Throwable $e) use (&$error, &$board, $retry, $loop, $port): void {
    if ($error !== null) {
        return; // already recovering
    }
    // a pulled cable also surfaces as an I2C timeout, so ask the system whether the port is there
    $gone = $port !== null && !file_exists($port);
    $error = match (true) {
        $gone => 'Lost the board: the USB cable is out.',
        $e instanceof I2cException => 'The board answers, the receiver does not. Unplug and replug the board to power-cycle it.',
        default => 'Lost the board: ' . $e->getMessage(),
    };
    printf("[%s] %s\n", date('H:i:s'), $error);
    try {
        $board?->close();   // a dead port left in the loop spins select()
    } catch (\Throwable) {
    }
    $board = null;
    $loop->addTimer(2.0, $retry);
};

$step = function () use (&$step, &$cursor, &$levels, &$sweeps, &$listening, &$error, &$radio, $frequencies, $fail, $loop): void {
    if ($listening !== null || $error !== null) {
        return;
    }
    try {
        $radio->tune($frequencies[$cursor], mute: true);
    } catch (I2cException | TransportException $e) {
        $fail($e);

        return;
    }
    $loop->addTimer(0.05, function () use (&$step, &$cursor, &$levels, &$sweeps, &$listening, &$error, &$radio, $frequencies, $fail): void {
        if ($listening !== null || $error !== null) {
            return;
        }
        try {
            $levels[$cursor] = $radio->status()['level'];
        } catch (I2cException | TransportException $e) {
            $fail($e);

            return;
        }
        $cursor = ($cursor + 1) % count($frequencies);
        if ($cursor === 0) {
            $sweeps++;
        }
        $step();
    });
};

$connect();

$respond = fn (int $code, string $type, string $body): string => sprintf(
    "HTTP/1.1 %d OK\r\nContent-Type: %s; charset=utf-8\r\nContent-Length: %d\r\nCache-Control: no-store\r\nConnection: close\r\n\r\n%s",
    $code, $type, strlen($body), $body,
);

$handle = function (string $method, string $path, string $body) use (
    &$cursor, &$levels, &$sweeps, &$listening, &$error, &$radio, $frequencies, $step, $fail, $respond,
): string {
    if ($method === 'GET' && $path === '/spectrum') {
        $points = [];
        foreach ($levels as $i => $level) {
            if ($level !== null) {
                $points[] = ['frequency' => $frequencies[$i], 'level' => $level, 'stereo' => false];
            }
        }
        $now = null;
        if ($listening !== null && $error === null) {
            try {
                $now = $radio->status();
            } catch (I2cException | TransportException $e) {
                $fail($e);
            }
        }

        return $respond(200, 'application/json', json_encode([
            'frequencies' => $frequencies,
            'levels' => $levels,
            'cursor' => $cursor,
            'sweeps' => $sweeps,
            'floor' => $points === [] ? null : Tea5767::noiseFloor($points),
            'stations' => array_column($sweeps > 0 ? Tea5767::stations($points) : [], 'frequency'),
            'listening' => $now ?? ($listening === null ? null : ['frequency' => $listening, 'level' => null]),
            'muted' => $radio?->isMuted() ?? false,
            'error' => $error,
        ]));
    }
    if ($method === 'POST' && $path === '/listen') {
        $mhz = (float) $body;
        if ($mhz >= Tea5767::MIN_MHZ && $mhz <= Tea5767::MAX_MHZ) {
            $listening = $mhz;          // while the receiver is lost, recovery tunes it
            printf("[%s] listening to %.1f MHz\n", date('H:i:s'), $mhz);
            if ($error === null) {
                try {
                    $radio->tune($mhz);
                } catch (I2cException | TransportException $e) {
                    $fail($e);
                }
            }
        }

        return $respond(200, 'text/plain', 'ok');
    }
    if ($method === 'POST' && $path === '/mute') {
        if ($error === null) {
            try {
                $radio->mute($body === '1');
            } catch (I2cException | TransportException $e) {
                $fail($e);
            }
        }

        return $respond(200, 'text/plain', 'ok');
    }
    if ($method === 'POST' && $path === '/sweep') {
        if ($listening !== null) {
            $listening = null;
            $step();
            printf("[%s] sweeping\n", date('H:i:s'));
        }

        return $respond(200, 'text/plain', 'ok');
    }
    if ($method === 'GET' && $path === '/') {
        return $respond(200, 'text/html', (string) file_get_contents(__DIR__ . '/fm-spectrum.html'));
    }

    return $respond(404, 'text/plain', 'not found');
};

$server = stream_socket_server("tcp://127.0.0.1:{$httpPort}", $errno, $errstr);
if ($server === false) {
    exit("Cannot listen on port {$httpPort}: {$errstr}\n");
}
stream_set_blocking($server, false);

$loop->addReadStream($server, function () use ($server, $loop, $handle): void {
    $client = @stream_socket_accept($server, 0);
    if ($client === false) {
        return;
    }
    stream_set_blocking($client, false);
    $buffer = '';
    $loop->addReadStream($client, function () use ($client, $loop, $handle, &$buffer): void {
        $chunk = fread($client, 65536);
        if ($chunk === false || $chunk === '') {
            if (feof($client)) {
                $loop->removeReadStream($client);
                fclose($client);
            }
            return;
        }
        $buffer .= $chunk;
        $headerEnd = strpos($buffer, "\r\n\r\n");
        if ($headerEnd === false) {
            return;
        }
        $head = substr($buffer, 0, $headerEnd);
        preg_match('/^Content-Length:\s*(\d+)/im', $head, $cl);
        $body = substr($buffer, $headerEnd + 4);
        if (strlen($body) < (int) ($cl[1] ?? 0)) {
            return; // body still arriving
        }
        [$method, $path] = explode(' ', strtok($head, "\r\n") ?: '', 3) + ['', '/'];
        // unregister before answering: /spectrum reads the chip, and that read ticks the loop
        $loop->removeReadStream($client);
        fwrite($client, $handle($method, $path, $body)); // ponytail: single write, responses are a few KB
        fclose($client);
    });
});

$step();
printf("FM spectrum — sweeping %.1f–%.1f MHz. Open http://localhost:%d\n", Tea5767::MIN_MHZ, Tea5767::MAX_MHZ, $httpPort);
$loop->run();
