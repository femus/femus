# femus GSM/AT Stack — Implementation Plan (План 5)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Steps use checkbox syntax.

**Goal:** AT-стек: `AtChannel` (строки, эхо, блокирующий send, unsolicited, prompt-режим CMGS) поверх Transport+Loop и `GsmModem` (SMS туда-обратно, регистрация в сети, сигнал) с тестами на записанных AT-транскриптах.

**Architecture:** Модем — serial-устройство на хосте (USB-serial/YP-01), Firmata не участвует. `AtChannel` регистрирует поток в event loop, собирает строки из чанков, различает ответ на команду / unsolicited-события; unsolicited, пришедшие ПОСРЕДИ команды, ставятся в очередь и доставляются после её завершения (pending снимается до доставки — колбэк может слать свои команды). `GsmModem` — высокоуровневые операции на транскриптах SIM800/SIM5216.

**Tech Stack:** PHP 8.2+/Pest; существующие Transport/SerialPort/InMemoryTransport/Loop.

## Global Constraints

- ВСЁ на английском (код, исключения, it(), доки); `<?php` + `declare(strict_types=1);`; final; докблоки только где типов не хватает
- Новый неймспейс `Femus\Gsm`; существующие API не трогать; TDD; Conventional Commits англ., без Claude/Co-Authored-By
- Терминальные строки ответа: `OK` (ok=true), `ERROR` / prefix `+CME ERROR` / prefix `+CMS ERROR` (ok=false)
- Эхо-фильтр: строка, равная отправленной команде (trim), отбрасывается
- Unsolicited во время команды — в очередь, доставка после завершения команды (сначала `pending = null`, потом доставка)
- Команда пишется как `$command . "\r"`; SMS-текст в prompt-режиме — `$payload . "\x1A"`; prompt = буфер начинается с `"> "` (без \n!)

---

### Task 1: AtChannel — строки, send, таймаут, unsolicited

**Files:**
- Create: `src/Gsm/AtChannel.php`, `src/Gsm/AtResponse.php`, `src/Gsm/AtException.php`
- Test: `tests/Gsm/AtChannelTest.php`

**Interfaces:**
- Consumes: `Femus\Transport\{Transport,InMemoryTransport}`, `Femus\Runtime\{Loop,StreamSelectLoop}`
- Produces:
  - `final class AtResponse` — `__construct(public readonly bool $ok, /** @var list<string> */ public readonly array $lines)`; `firstLine(): ?string`
  - `final class AtException extends \RuntimeException`
  - `final class AtChannel` — `__construct(Transport $transport, Loop $loop, float $commandTimeout = 5.0)` (регистрирует read stream: ingest chunks); `send(string $command): AtResponse` (блокирующий, `AtException("Modem did not respond to '{$command}' within ...s")` по таймауту); `onUnsolicited(callable $listener): void` (fn(string $line)); `isBusy(): bool`

- [ ] **Step 1: Падающие тесты**

`tests/Gsm/AtChannelTest.php`:
```php
use Femus\Gsm\AtChannel;
use Femus\Gsm\AtException;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\InMemoryTransport;

function atChannel(float $timeout = 1.0): array
{
    $transport = new InMemoryTransport();
    $loop = new StreamSelectLoop();

    return [new AtChannel($transport, $loop, $timeout), $transport, $loop];
}

it('sends a command and collects the response until OK', function () {
    [$channel, $transport, $loop] = atChannel();
    $loop->addTimer(0.01, fn () => $transport->feed("AT+CSQ\r\n+CSQ: 21,0\r\n\r\nOK\r\n"));
    $response = $channel->send('AT+CSQ');
    expect($transport->written)->toBe("AT+CSQ\r")
        ->and($response->ok)->toBeTrue()
        ->and($response->lines)->toBe(['+CSQ: 21,0'])   // echo and empty lines stripped
        ->and($response->firstLine())->toBe('+CSQ: 21,0');
});

it('marks ERROR responses as not ok', function () {
    [$channel, $transport, $loop] = atChannel();
    $loop->addTimer(0.01, fn () => $transport->feed("\r\n+CME ERROR: 10\r\n"));
    $response = $channel->send('AT+CPIN?');
    expect($response->ok)->toBeFalse()
        ->and($response->lines)->toBe(['+CME ERROR: 10']);
});

it('survives responses split across chunks', function () {
    [$channel, $transport, $loop] = atChannel();
    $loop->addTimer(0.01, fn () => $transport->feed("+CSQ: 2"));
    $loop->addTimer(0.02, fn () => $transport->feed("1,0\r\nOK\r\n"));
    $response = $channel->send('AT+CSQ');
    expect($response->lines)->toBe(['+CSQ: 21,0'])->and($response->ok)->toBeTrue();
});

it('throws on timeout', function () {
    [$channel] = atChannel(timeout: 0.05);
    $channel->send('AT');
})->throws(AtException::class);

it('delivers unsolicited lines outside a command', function () {
    [$channel, $transport, $loop] = atChannel();
    $seen = [];
    $channel->onUnsolicited(function (string $line) use (&$seen, $loop) {
        $seen[] = $line;
        $loop->stop();
    });
    $transport->feed("\r\n+CMTI: \"SM\",3\r\n");
    $loop->addTimer(1.0, fn () => $loop->stop());
    $loop->run();
    expect($seen)->toBe(['+CMTI: "SM",3']);
});

it('queues unsolicited lines during a command and delivers them after', function () {
    [$channel, $transport, $loop] = atChannel();
    $order = [];
    $channel->onUnsolicited(function (string $line) use (&$order, $channel) {
        $order[] = 'unsolicited:' . $line . ':busy=' . ($channel->isBusy() ? '1' : '0');
    });
    $loop->addTimer(0.01, fn () => $transport->feed("+CMTI: \"SM\",7\r\nOK\r\n"));
    $response = $channel->send('AT');
    $order[] = 'response:' . ($response->ok ? 'ok' : 'error');
    // unsolicited delivered after command completed (busy=0), before send() returned
    expect($order)->toBe(['unsolicited:+CMTI: "SM",7:busy=0', 'response:ok']);
});
```

- [ ] **Step 2: Прогнать — падают** (`Class "Femus\Gsm\AtChannel" not found`)

- [ ] **Step 3: Реализация**

`src/Gsm/AtException.php`:
```php
namespace Femus\Gsm;

final class AtException extends \RuntimeException
{
}
```

`src/Gsm/AtResponse.php`:
```php
namespace Femus\Gsm;

final class AtResponse
{
    /** @param list<string> $lines */
    public function __construct(
        public readonly bool $ok,
        public readonly array $lines,
    ) {
    }

    public function firstLine(): ?string
    {
        return $this->lines[0] ?? null;
    }
}
```

`src/Gsm/AtChannel.php`:
```php
namespace Femus\Gsm;

use Femus\Runtime\Loop;
use Femus\Transport\Transport;

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
```

- [ ] **Step 4: `composer test` — зелёные**
- [ ] **Step 5: Commit** `feat: AT channel — line assembly, blocking send, unsolicited events`

---

### Task 2: Prompt-режим для отправки SMS (CMGS)

**Files:**
- Modify: `src/Gsm/AtChannel.php`
- Test: дополнить `tests/Gsm/AtChannelTest.php`

**Interfaces:**
- Produces: `AtChannel::sendExpectingPrompt(string $command, string $payload): AtResponse` — пишет `$command."\r"`, ждёт появления `"> "` в начале буфера (тот же таймаут, AtException при отсутствии), затем пишет `$payload."\x1A"` и ждёт обычный терминал OK/ERROR

- [ ] **Step 1: Падающие тесты** (добавить в AtChannelTest.php)

```php
it('sends a payload after the prompt', function () {
    [$channel, $transport, $loop] = atChannel();
    $loop->addTimer(0.01, fn () => $transport->feed("\r\n> "));
    $loop->addTimer(0.03, fn () => $transport->feed("\r\n+CMGS: 4\r\n\r\nOK\r\n"));
    $response = $channel->sendExpectingPrompt('AT+CMGS="+79161234567"', 'Hello');
    expect($transport->written)->toBe("AT+CMGS=\"+79161234567\"\rHello\x1A")
        ->and($response->ok)->toBeTrue()
        ->and($response->lines)->toBe(['+CMGS: 4']);
});

it('throws when the prompt never arrives', function () {
    [$channel] = atChannel(timeout: 0.05);
    $channel->sendExpectingPrompt('AT+CMGS="+79161234567"', 'Hello');
})->throws(AtException::class);
```

- [ ] **Step 2: Прогнать — падают**

- [ ] **Step 3: Реализация** — в AtChannel:
```php
    private bool $awaitingPrompt = false;

    private bool $promptSeen = false;

    public function sendExpectingPrompt(string $command, string $payload): AtResponse
    {
        $this->pendingCommand = $command;
        $this->pendingLines = [];
        $this->pendingOk = null;
        $this->awaitingPrompt = true;
        $this->promptSeen = false;
        $this->transport->write($command . "\r");

        $deadline = hrtime(true) / 1e9 + $this->commandTimeout;
        try {
            while (!$this->promptSeen) {
                $remaining = $deadline - hrtime(true) / 1e9;
                if ($remaining <= 0) {
                    throw new AtException(sprintf("Modem did not show the SMS prompt for '%s'", $command));
                }
                $this->loop->tick(min(0.05, $remaining));
            }
        } catch (AtException $e) {
            $this->pendingCommand = null;
            $this->awaitingPrompt = false;
            throw $e;
        }
        $this->awaitingPrompt = false;
        $this->transport->write($payload . "\x1A");

        while ($this->pendingOk === null) {
            $remaining = $deadline - hrtime(true) / 1e9;
            if ($remaining <= 0) {
                $this->pendingCommand = null;
                throw new AtException(sprintf("Modem did not confirm the SMS for '%s'", $command));
            }
            $this->loop->tick(min(0.05, $remaining));
        }

        $response = new AtResponse($this->pendingOk, $this->pendingLines);
        $this->pendingCommand = null;
        $this->flushQueuedUnsolicited();

        return $response;
    }
```
и в начале `ingest()` (до цикла построчного разбора):
```php
        if ($this->awaitingPrompt && str_starts_with(ltrim($this->buffer, "\r\n"), '> ')) {
            $this->promptSeen = true;
            $this->buffer = substr(ltrim($this->buffer, "\r\n"), 2);
        }
```

- [ ] **Step 4: `composer test` — зелёные**
- [ ] **Step 5: Commit** `feat: AT prompt mode for SMS submission`

---

### Task 3: GsmModem + Sms

**Files:**
- Create: `src/Gsm/GsmModem.php`, `src/Gsm/Sms.php`
- Test: `tests/Gsm/GsmModemTest.php`

**Interfaces:**
- Consumes: AtChannel (Tasks 1–2), SerialPort, StreamSelectLoop
- Produces:
  - `final readonly class Femus\Gsm\Sms` — `__construct(public string $from, public string $text)`
  - `final class GsmModem` — `__construct(AtChannel $channel)`; `static open(string $device, int $baudRate = 115200, ?Loop $loop = null): self` (SerialPort + AtChannel); `init(): void` (ATE0, AT+CMGF=1, AT+CNMI=2,1,0,0,0 — каждый должен вернуть ok, иначе `AtException("Modem rejected '{$cmd}'")`); `isRegistered(): bool` (AT+CREG? → `+CREG: <n>,<stat>`, stat 1|5 → true); `signalQuality(): ?int` (AT+CSQ → `+CSQ: <rssi>,<ber>`; 99 → null); `sendSms(string $number, string $text): void` (sendExpectingPrompt; !ok → AtException); `readSms(int $index): Sms` (AT+CMGR=n: первая строка `+CMGR: "<status>","<from>",...`, остальные — текст, join "\n"; отсутствие/не-ok → AtException); `deleteSms(int $index): void`; `onSmsReceived(callable $listener): void` (fn(Sms); на unsolicited `+CMTI: "<mem>",<n>` — readSms(n) и вызвать листенер; безопасно: unsolicited доставляются только когда канал не занят)

- [ ] **Step 1: Падающие тесты**

`tests/Gsm/GsmModemTest.php`:
```php
use Femus\Gsm\AtChannel;
use Femus\Gsm\AtException;
use Femus\Gsm\GsmModem;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\InMemoryTransport;

function gsmModem(): array
{
    $transport = new InMemoryTransport();
    $loop = new StreamSelectLoop();
    $modem = new GsmModem(new AtChannel($transport, $loop, 1.0));

    return [$modem, $transport, $loop];
}

it('reports network registration from CREG', function () {
    [$modem, $transport, $loop] = gsmModem();
    $loop->addTimer(0.01, fn () => $transport->feed("+CREG: 0,1\r\nOK\r\n"));
    expect($modem->isRegistered())->toBeTrue();
    $loop->addTimer(0.01, fn () => $transport->feed("+CREG: 0,2\r\nOK\r\n"));
    expect($modem->isRegistered())->toBeFalse();
});

it('parses signal quality and maps 99 to null', function () {
    [$modem, $transport, $loop] = gsmModem();
    $loop->addTimer(0.01, fn () => $transport->feed("+CSQ: 21,0\r\nOK\r\n"));
    expect($modem->signalQuality())->toBe(21);
    $loop->addTimer(0.01, fn () => $transport->feed("+CSQ: 99,99\r\nOK\r\n"));
    expect($modem->signalQuality())->toBeNull();
});

it('sends an sms through the prompt flow', function () {
    [$modem, $transport, $loop] = gsmModem();
    $loop->addTimer(0.01, fn () => $transport->feed("\r\n> "));
    $loop->addTimer(0.03, fn () => $transport->feed("+CMGS: 4\r\nOK\r\n"));
    $modem->sendSms('+79161234567', 'Hello');
    expect($transport->written)->toBe("AT+CMGS=\"+79161234567\"\rHello\x1A");
});

it('reads a multiline sms', function () {
    [$modem, $transport, $loop] = gsmModem();
    $loop->addTimer(0.01, fn () => $transport->feed(
        "+CMGR: \"REC UNREAD\",\"+79161234567\",,\"26/08/04,12:00:00+12\"\r\nfirst line\r\nsecond line\r\nOK\r\n",
    ));
    $sms = $modem->readSms(3);
    expect($sms->from)->toBe('+79161234567')
        ->and($sms->text)->toBe("first line\nsecond line");
});

it('emits Sms objects on CMTI notifications', function () {
    [$modem, $transport, $loop] = gsmModem();
    $received = null;
    $modem->onSmsReceived(function ($sms) use (&$received, $loop) {
        $received = $sms;
        $loop->stop();
    });
    // CMTI arrives while idle; the handler then issues AT+CMGR=3 — answer it a moment later
    $loop->addTimer(0.01, fn () => $transport->feed("\r\n+CMTI: \"SM\",3\r\n"));
    $loop->addTimer(0.05, fn () => $transport->feed(
        "+CMGR: \"REC UNREAD\",\"+79161234567\",,\"26/08/04,12:00:00+12\"\r\nPing\r\nOK\r\n",
    ));
    $loop->addTimer(1.0, fn () => $loop->stop());
    $loop->run();
    expect($received)->not->toBeNull()
        ->and($received->from)->toBe('+79161234567')
        ->and($received->text)->toBe('Ping')
        ->and($transport->written)->toContain('AT+CMGR=3');
});

it('init sends the setup sequence and fails loudly on rejection', function () {
    [$modem, $transport, $loop] = gsmModem();
    $loop->addTimer(0.01, fn () => $transport->feed("OK\r\n"));
    $loop->addTimer(0.03, fn () => $transport->feed("OK\r\n"));
    $loop->addTimer(0.05, fn () => $transport->feed("OK\r\n"));
    $modem->init();
    expect($transport->written)->toBe("ATE0\rAT+CMGF=1\rAT+CNMI=2,1,0,0,0\r");
});
```

- [ ] **Step 2: Прогнать — падают**

- [ ] **Step 3: Реализация**

`src/Gsm/Sms.php`:
```php
namespace Femus\Gsm;

final readonly class Sms
{
    public function __construct(
        public string $from,
        public string $text,
    ) {
    }
}
```

`src/Gsm/GsmModem.php`:
```php
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
```

- [ ] **Step 4: `composer test` — зелёные**
- [ ] **Step 5: Commit** `feat: GsmModem — SMS send/receive, registration and signal queries`

---

### Task 4: Пример, док, README, чеклист

**Files:**
- Create: `examples/sms-send.php`, `docs/devices/gsm-modem.md`
- Modify: `README.md` (Status), `docs/hardware-runs.md` (новый релиз-чеклист)

- [ ] **Step 1: Написать** (английский)

`examples/sms-send.php`:
```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Gsm\GsmModem;

// Usage: php examples/sms-send.php /dev/cu.usbserial-XXXX +79161234567 "Hello from femus"
[$script, $port, $number, $text] = $argv + [null, null, null, 'Hello from femus'];
if ($port === null || $number === null) {
    exit("Usage: php examples/sms-send.php <serial-port> <number> [text]\n");
}

$modem = GsmModem::open($port);
$modem->init();

if (!$modem->isRegistered()) {
    exit("Not registered on the network yet — check the SIM and antenna, then retry.\n");
}

printf("Signal: %s\n", $modem->signalQuality() ?? 'unknown');
$modem->sendSms($number, $text);
echo "SMS sent.\n";
```

`docs/devices/gsm-modem.md`: intro (works with any AT modem over a serial port: SIM800L, SIM5216E, Cinterion EHS5-E); ⚠️ POWER WARNING box for SIM800L (3.4–4.2 V, 2 A bursts — never the Arduino 5V pin; use MB102/LiPo, common GND); wiring via USB-TTL (YP-01: TXD↔RXD cross, GND common; SIM800L logic is ~2.8V — level-aware note: use a divider on module RXD); baud note (SIM800L autobauds — send AT first at 115200, fall back to 9600: `GsmModem::open($port, 9600)`); code snippet (open/init/isRegistered/signalQuality/sendSms/onSmsReceived + `$loop->run()` for receiving); receiving example inline (onSmsReceived printf).

`README.md` Status: add `GSM/AT stack (Femus\Gsm: AtChannel, GsmModem — SMS send/receive)` to ready; drop GSM/AT from roadmap list.

`docs/hardware-runs.md` append:
```markdown
## Release 2026-08-04-gsm-at

### Testing Checklist (Pending Human Execution)

1. Power the modem correctly (SIM800L: external 3.4–4.2 V source, common GND — see docs/devices/gsm-modem.md)
2. Insert a SIM (PIN disabled), connect via USB-TTL, `php examples/sms-send.php <port> <your number> "test"`
3. SMS arrives on the phone; reply to it — onSmsReceived demo prints it
4. Record results below

### Run 1
- Date: (pending)
- Modem: (pending)
- Result: (pending)
```

- [ ] **Step 2: `php -l examples/sms-send.php` + `composer test` — зелёные**
- [ ] **Step 3: Commit** `docs: SMS example, GSM modem guide and hardware checklist`
