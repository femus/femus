<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;
use Femus\Contracts\RadioLink;
use Femus\Contracts\RadioMessage;

// Browser chat over the 433 MHz radio: messages from the peer node appear in the page,
// what you type in the page goes out over the radio. No dependencies — a minimal HTTP
// server on stream sockets, driven by the femus event loop next to the board.
//
// Usage: php examples/radio-web-chat.php [port] [my-address] [peer-address] [rx-pin] [tx-pin] [http-port]
//   then open http://localhost:8080
$board = Board::firmata($argv[1] ?? null);
$address = (int) ($argv[2] ?? 1);
$peer = (int) ($argv[3] ?? RadioLink::BROADCAST);
$rxPin = (int) ($argv[4] ?? 11);
$txPin = (int) ($argv[5] ?? 12);
$httpPort = (int) ($argv[6] ?? 8080);

$radio = $board->radioLink($address, $rxPin, $txPin);

/** @var list<array{id:int,from:string,text:string,at:string}> $messages */
$messages = [];
$push = function (string $from, string $text) use (&$messages): void {
    $messages[] = ['id' => count($messages) + 1, 'from' => $from, 'text' => $text, 'at' => date('H:i:s')];
};

$radio->onMessage(function (RadioMessage $m) use ($push): void {
    $push("node {$m->from}", $m->message);
    printf("[%s] node %d: %s\n", date('H:i:s'), $m->from, $m->message);
});

$respond = fn (int $code, string $type, string $body): string => sprintf(
    "HTTP/1.1 %d OK\r\nContent-Type: %s; charset=utf-8\r\nContent-Length: %d\r\nCache-Control: no-store\r\nConnection: close\r\n\r\n%s",
    $code, $type, strlen($body), $body,
);

$handle = function (string $method, string $path, string $body) use (&$messages, $push, $radio, $peer, $address, $respond): string {
    $route = strtok($path, '?');
    if ($method === 'GET' && $route === '/messages') {
        parse_str((string) parse_url($path, PHP_URL_QUERY), $q);
        $since = (int) ($q['since'] ?? 0);
        $fresh = array_values(array_filter($messages, fn (array $m) => $m['id'] > $since));
        return $respond(200, 'application/json', json_encode($fresh, JSON_UNESCAPED_UNICODE));
    }
    if ($method === 'POST' && $route === '/send') {
        $text = mb_strcut(trim($body), 0, 50); // radio frames carry at most 50 bytes
        if ($text !== '') {
            $radio->send($peer, $text);
            $push('me', $text);
        }
        return $respond(200, 'text/plain', 'ok');
    }
    if ($method === 'GET' && $route === '/') {
        $html = file_get_contents(__DIR__ . '/radio-web-chat.html');
        $peerLabel = $peer === RadioLink::BROADCAST ? 'broadcast' : "node {$peer}";
        return $respond(200, 'text/html', strtr($html, ['{{me}}' => "node {$address}", '{{peer}}' => $peerLabel]));
    }
    return $respond(404, 'text/plain', 'not found');
};

$server = stream_socket_server("tcp://127.0.0.1:{$httpPort}", $errno, $errstr);
if ($server === false) {
    exit("Cannot listen on port {$httpPort}: {$errstr}\n");
}
stream_set_blocking($server, false);
$loop = $board->loop();

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
        fwrite($client, $handle($method, $path, $body)); // ponytail: single write, responses are a few KB and fit the socket buffer
        $loop->removeReadStream($client);
        fclose($client);
    });
});

printf("Radio web chat — node %d ⇄ %s. Open http://localhost:%d\n",
    $address, $peer === RadioLink::BROADCAST ? 'broadcast' : "node {$peer}", $httpPort);
$board->run();
