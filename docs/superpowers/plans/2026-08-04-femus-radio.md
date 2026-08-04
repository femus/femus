# femus 433 MHz Radio — Implementation Plan (План 6, флагман «радио-мессенджер»)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Steps use checkbox syntax.

**Goal:** Радиоканал 433 МГц: sysex-протокол 0x0D, прошивочный RadioFeature (RadioHead RH_ASK, заголовки from/to + CRC), PHP `RadioLink`, мост-скетч для узла-телефона (HM-10 BLE ⇄ радио), пример-чат и схемы.

**Architecture:** Как HX711: прошивка делает тайминги (RH_ASK 2000 bps, битбэнг), PHP шлёт/принимает пакеты sysex-фреймами. Адресация: 0–126, broadcast 127 (в прошивке мапится на RH_BROADCAST_ADDRESS 0xFF); фильтрация по адресу и CRC — аппаратно в RH_ASK (validateRxBuf). Узел B (iPhone) — отдельный автономный мост-скетч без Firmata.

**Tech Stack:** PHP 8.2+/Pest; C++ RadioHead (RH_ASK) + ConfigurableFirmata; arduino-cli.

## Global Constraints

- ВСЁ на английском; `<?php` + `declare(strict_types=1);`; final; TDD; Conventional Commits англ., без Claude/Co-Authored-By
- Протокол: `FEMUS_RADIO = 0x0D`; `RADIO_ATTACH = 0x00` (payload: address, rxPin, txPin — по септету); `RADIO_SEND = 0x01` (payload: toAddress, далее сообщение 7-битными парами LSB+бит7); `RADIO_RECV = 0x02` (payload: fromAddress, toAddress, далее пары)
- Адреса 0–126; `BROADCAST = 127`; сообщение ≤ 50 байт (лимит RH_ASK 60, запас)
- Пары: byte → chr(b & 0x7F), chr((b >> 7) & 0x01); декод: b = lsb | (msb & 0x01) << 7
- ⚠️ RAM Nano: если после добавления RadioFeature в FemusFirmata глобальные переменные > 85% — вынести радио в ОТДЕЛЬНЫЙ скетч firmware/FemusRadioFirmata (digital I/O + radio, без HX711/I2C/analog) и задокументировать «какая прошивка для какого узла»

---

### Task 1: PHP-протокол радио

**Files:**
- Modify: `src/Adapter/Firmata/Firmata.php` (константы), `src/Adapter/Firmata/FirmataEncoder.php` (radioAttach, radioSend; сменить видимость `encode7bitPairs` private → public)
- Create: `src/Adapter/Firmata/RadioMessageFrame.php`
- Test: `tests/Adapter/Firmata/RadioProtocolTest.php`

**Interfaces:**
- Produces:
  - `Firmata::FEMUS_RADIO = 0x0D`, `RADIO_ATTACH = 0x00`, `RADIO_SEND = 0x01`, `RADIO_RECV = 0x02`
  - `FirmataEncoder::radioAttach(int $address, int $rxPin, int $txPin): string` → `"\xF0\x0D\x00" . chr(addr) . chr(rx) . chr(tx) . "\xF7"`
  - `FirmataEncoder::radioSend(int $toAddress, string $message): string` → `"\xF0\x0D\x01" . chr(to) . encode7bitPairs(msg) . "\xF7"`
  - `FirmataEncoder::encode7bitPairs(string $bytes): string` — теперь public static
  - `final class RadioMessageFrame` — `public readonly int $from`, `public readonly int $to`, `public readonly string $message`; `static fromSysexPayload(string $payload): ?self` (null если не 0x0D/0x02 или длина < 4; пары декодируются с позиции 4)

- [ ] **Step 1: Падающие тесты**

`tests/Adapter/Firmata/RadioProtocolTest.php`:
```php
use Femus\Adapter\Firmata\FirmataEncoder;
use Femus\Adapter\Firmata\RadioMessageFrame;

it('encodes radio attach', function () {
    expect(FirmataEncoder::radioAttach(1, 11, 12))->toBe("\xF0\x0D\x00\x01\x0B\x0C\xF7");
});

it('encodes radio send with 7-bit pairs', function () {
    // 'H' = 0x48 → 48 00, 'i' = 0x69 → 69 00
    expect(FirmataEncoder::radioSend(3, 'Hi'))->toBe("\xF0\x0D\x01\x03\x48\x00\x69\x00\xF7");
});

it('encodes bytes above 0x7F in send', function () {
    // 0xC3 → 43 01 (UTF-8 continuation bytes survive the link)
    expect(FirmataEncoder::radioSend(1, "\xC3"))->toBe("\xF0\x0D\x01\x01\x43\x01\xF7");
});

it('decodes a received frame', function () {
    $frame = RadioMessageFrame::fromSysexPayload("\x0D\x02\x02\x01\x48\x00\x69\x00");
    expect($frame)->not->toBeNull()
        ->and($frame->from)->toBe(2)
        ->and($frame->to)->toBe(1)
        ->and($frame->message)->toBe('Hi');
});

it('returns null for foreign payloads', function () {
    expect(RadioMessageFrame::fromSysexPayload("\x0E\x01\x64\x00\x00\x00\x00"))->toBeNull()
        ->and(RadioMessageFrame::fromSysexPayload("\x0D\x00\x01\x0B\x0C"))->toBeNull()
        ->and(RadioMessageFrame::fromSysexPayload("\x0D\x02\x02"))->toBeNull();
});
```

- [ ] **Step 2: Прогнать — падают** (`undefined method ... radioAttach`)
- [ ] **Step 3: Реализация** (по Interfaces; RadioMessageFrame — по образцу Hx711Reading: guard длины/командных байтов, цикл пар `for ($i = 4; $i + 1 < strlen($payload); $i += 2) { $message .= chr((ord($payload[$i]) & 0x7F) | ((ord($payload[$i + 1]) & 0x01) << 7)); }`)
- [ ] **Step 4: `composer test` — зелёные**
- [ ] **Step 5: Commit** `feat: 433 MHz radio sysex protocol — attach/send encoders and frame decoder`

---

### Task 2: Контракт RadioLink + Fake и Firmata реализации

**Files:**
- Create: `src/Contracts/RadioLink.php`, `src/Contracts/RadioMessage.php`, `src/Adapter/Fake/FakeRadioLink.php`, `src/Adapter/Firmata/FirmataRadioLink.php`
- Modify: `src/Contracts/BoardInterface.php`, `src/Adapter/Fake/FakeBoard.php`, `src/Adapter/Firmata/FirmataBoard.php`
- Test: `tests/Adapter/Fake/FakeRadioLinkTest.php`, `tests/Adapter/Firmata/FirmataRadioLinkTest.php` (helper `readyRadioBoard` — имя уникально)

**Interfaces:**
- Produces:
  - `final readonly class Femus\Contracts\RadioMessage` — `__construct(public int $from, public int $to, public string $message)`
  - `interface Femus\Contracts\RadioLink` — `public const BROADCAST = 127;` `send(int $toAddress, string $message): void`, `onMessage(callable $listener): void` (fn(RadioMessage)), `address(): int`
  - `FakeRadioLink implements RadioLink` — `__construct(private readonly int $address)`; `/** @var list<array{0: int, 1: string}> */ public array $sent = [];` (send пишет [to, message] и валидирует как Firmata-вариант); `simulateMessage(int $from, int $to, string $message): void` (создаёт RadioMessage, зовёт листенеры)
  - Валидация в send (обе реализации): to вне 0–127 → `InvalidArgumentException("Radio address must be 0-127")`; strlen(message) > 50 → `InvalidArgumentException("Radio message is limited to 50 bytes")`; пустое сообщение → InvalidArgumentException
  - `FirmataRadioLink implements RadioLink` — `__construct(Transport, FirmataParser, int $address, int $rxPin, int $txPin)`: attach в конструкторе, onSysex → RadioMessageFrame → RadioMessage → листенеры. Докблок: one radio per board (no channel id in the protocol)
  - `BoardInterface::radioLink(int $address, int $rxPin = 11, int $txPin = 12): RadioLink`; FakeBoard: кэш по адресу + `fakeRadio(int $address): FakeRadioLink` + `simulateRadioMessage(int $address, int $from, int $to, string $message): void`; FirmataBoard: кэш по адресу

- [ ] **Step 1: Падающие тесты**

`tests/Adapter/Fake/FakeRadioLinkTest.php`:
```php
use Femus\Adapter\Fake\FakeBoard;
use Femus\Contracts\RadioLink;
use Femus\Contracts\RadioMessage;
use Femus\Runtime\StreamSelectLoop;

it('records sent messages', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $radio = $board->radioLink(1);
    $radio->send(RadioLink::BROADCAST, 'ping');
    expect($board->fakeRadio(1)->sent)->toBe([[127, 'ping']])
        ->and($radio->address())->toBe(1);
});

it('delivers simulated incoming messages', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $radio = $board->radioLink(1);
    $seen = null;
    $radio->onMessage(function (RadioMessage $m) use (&$seen) { $seen = $m; });
    $board->simulateRadioMessage(1, from: 2, to: 1, message: 'Hi');
    expect($seen->from)->toBe(2)->and($seen->message)->toBe('Hi');
});

it('rejects out-of-range address and oversized message', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $radio = $board->radioLink(1);
    expect(fn () => $radio->send(200, 'x'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $radio->send(2, str_repeat('a', 51)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $radio->send(2, ''))->toThrow(InvalidArgumentException::class);
});
```

`tests/Adapter/Firmata/FirmataRadioLinkTest.php`:
```php
use Femus\Adapter\Firmata\FirmataBoard;
use Femus\Contracts\RadioMessage;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\InMemoryTransport;

function readyRadioBoard(InMemoryTransport $transport): FirmataBoard
{
    $board = new FirmataBoard($transport, new StreamSelectLoop());
    $transport->feed("\xF9\x02\x05");
    $board->awaitReady();

    return $board;
}

it('sends the attach frame on first request', function () {
    $transport = new InMemoryTransport();
    $board = readyRadioBoard($transport);
    $transport->written = '';
    $board->radioLink(1);
    expect($transport->written)->toBe("\xF0\x0D\x00\x01\x0B\x0C\xF7");
});

it('send writes the encoded frame', function () {
    $transport = new InMemoryTransport();
    $board = readyRadioBoard($transport);
    $radio = $board->radioLink(1);
    $transport->written = '';
    $radio->send(3, 'Hi');
    expect($transport->written)->toBe("\xF0\x0D\x01\x03\x48\x00\x69\x00\xF7");
});

it('delivers incoming frames and ignores foreign sysex', function () {
    $transport = new InMemoryTransport();
    $board = readyRadioBoard($transport);
    $radio = $board->radioLink(1);
    $seen = null;
    $radio->onMessage(function (RadioMessage $m) use (&$seen, $board) {
        $seen = $m;
        $board->stop();
    });
    $transport->feed("\xF0\x79\x02\x05\xF7");
    $transport->feed("\xF0\x0D\x02\x02\x01\x48\x00\x69\x00\xF7");
    $board->loop()->addTimer(1.0, fn () => $board->stop());
    $board->run();
    expect($seen->from)->toBe(2)->and($seen->to)->toBe(1)->and($seen->message)->toBe('Hi');
});
```

- [ ] **Step 2: Прогнать — падают**
- [ ] **Step 3: Реализация** (по образцу ScaleInput/FirmataScaleInput; вынести общую валидацию send в приватный метод каждой реализации — без преждевременного трейта)
- [ ] **Step 4: `composer test` — зелёные**
- [ ] **Step 5: Commit** `feat: RadioLink contract with Fake and Firmata implementations`

---

### Task 3: Прошивка — RadioFeature

**Files:**
- Create: `firmware/FemusFirmata/RadioFeature.h`
- Modify: `firmware/FemusFirmata/FemusFirmata.ino` (подключить RadioFeature; следовать фактическому стилю файла — Hx711Feature уже показывает реальный API: `report(bool elapsed) override` и т.п.), `firmware/README.md`

**Interfaces:**
- Consumes: байты Task 1 (идентичны)
- Produces: RadioFeature: ATTACH → `new RH_ASK(2000, rxPin, txPin)` + `init()` + `setThisAddress(addr)` (фильтрация to/broadcast и CRC — внутри RH_ASK::validateRxBuf); SEND → декодировать пары в buf[50], `setHeaderFrom(address)`, `setHeaderTo(to == 0x7F ? RH_BROADCAST_ADDRESS : to)`, `send()` + `waitPacketSent()`; в report() (каждый проход loop) — `recv()` → sysex RECV с from/to (RH_BROADCAST_ADDRESS → 0x7F) и парами

- [ ] **Step 1: Написать RadioFeature.h**

```cpp
#pragma once

#include <ConfigurableFirmata.h>
#include <FirmataFeature.h>
#include <RH_ASK.h>

#define FEMUS_RADIO_COMMAND 0x0D
#define FEMUS_RADIO_ATTACH 0x00
#define FEMUS_RADIO_SEND 0x01
#define FEMUS_RADIO_RECV 0x02
#define FEMUS_RADIO_BROADCAST 0x7F
#define FEMUS_RADIO_MAX_LEN 50

// 433 MHz ASK link (FS1000A + MX-RM-5V) with from/to headers and CRC via RadioHead.
// One radio per board: the protocol carries no channel id.
class RadioFeature : public FirmataFeature
{
public:
  boolean handlePinMode(byte pin, int mode) override
  {
    return false;
  }

  void handleCapability(byte pin) override
  {
  }

  void reset() override
  {
    attached = false;
  }

  boolean handleSysex(byte command, byte argc, byte *argv) override
  {
    if (command != FEMUS_RADIO_COMMAND) {
      return false;
    }
    if (argc >= 4 && argv[0] == FEMUS_RADIO_ATTACH) {
      if (driver != NULL) {
        delete driver;
      }
      address = argv[1];
      driver = new RH_ASK(2000, argv[2], argv[3]);
      driver->init();
      driver->setThisAddress(address);
      attached = true;
    } else if (attached && argc >= 2 && argv[0] == FEMUS_RADIO_SEND) {
      byte to = argv[1] == FEMUS_RADIO_BROADCAST ? RH_BROADCAST_ADDRESS : argv[1];
      byte buf[FEMUS_RADIO_MAX_LEN];
      byte len = 0;
      for (byte i = 2; i + 1 < argc && len < sizeof(buf); i += 2) {
        buf[len++] = (argv[i] & 0x7F) | ((argv[i + 1] & 0x01) << 7);
      }
      driver->setHeaderFrom(address);
      driver->setHeaderTo(to);
      driver->send(buf, len);
      driver->waitPacketSent();
    }
    return true;
  }

  // Called every sketch loop iteration: RH_ASK reception must be polled often.
  void report(bool elapsed) override
  {
    (void) elapsed;
    if (!attached) {
      return;
    }
    uint8_t buf[RH_ASK_MAX_MESSAGE_LEN];
    uint8_t len = sizeof(buf);
    if (!driver->recv(buf, &len)) {
      return;
    }
    byte to = driver->headerTo() == RH_BROADCAST_ADDRESS ? FEMUS_RADIO_BROADCAST : driver->headerTo();
    Firmata.startSysex();
    Firmata.write(FEMUS_RADIO_COMMAND);
    Firmata.write(FEMUS_RADIO_RECV);
    Firmata.write(driver->headerFrom() & 0x7F);
    Firmata.write(to & 0x7F);
    for (uint8_t i = 0; i < len; i++) {
    Firmata.write(buf[i] & 0x7F);
      Firmata.write((buf[i] >> 7) & 0x01);
    }
    Firmata.endSysex();
  }

private:
  RH_ASK *driver = NULL;
  byte address = 0;
  bool attached = false;
};
```
Если реальные заголовки RadioHead/ConfigurableFirmata требуют иного (имена методов, сигнатура report) — адаптировать по фактическим заголовкам установленных библиотек, задокументировать отклонения в отчёте. В .ino: объявить `RadioFeature radio;`, `firmataExt.addFeature(radio);`, в loop добавить `radio.report(elapsed);` рядом с hx711.

- [ ] **Step 2: Компиляция и контроль RAM**

`arduino-cli lib install RadioHead` затем `arduino-cli compile --fqbn arduino:avr:nano:cpu=atmega328old firmware/FemusFirmata`. Смотреть строку «Глобальные переменные используют X%»:
- ≤ 85% → оставить единую прошивку;
- > 85% → создать `firmware/FemusRadioFirmata/` (копия .ino БЕЗ Hx711Feature/I2CFirmata/AnalogInputFirmata: только Digital In/Out + RadioFeature), убрать RadioFeature из основной FemusFirmata, скомпилировать оба, в firmware/README.md таблица «узел → прошивка».
Итог компиляции (байты/проценты) — в отчёт.

- [ ] **Step 3: Обновить firmware/README.md** — секция Radio: протокол 0x0D (таблица ATTACH/SEND/RECV), адреса 0–126 + broadcast 127, лимит 50 байт, антенна 17.3 см
- [ ] **Step 4: `composer test` — PHP не тронут, зелёный**
- [ ] **Step 5: Commit** `feat: RadioFeature — 433 MHz ASK link in FemusFirmata`

---

### Task 4: Мост-скетч узла-телефона (HM-10 ⇄ радио)

**Files:**
- Create: `firmware/RadioBleBridge/RadioBleBridge.ino`, `docs/devices/radio-ble-bridge.md`

**Interfaces:**
- Produces: автономный скетч (БЕЗ Firmata): строки из BLE-UART (HM-10, SoftwareSerial пины 7=RX, 8=TX, 9600) по \n → radio.send на PEER_ADDRESS; radio.recv → в BLE + println. NODE_ADDRESS 2, PEER_ADDRESS 1 (константами вверху скетча)

- [ ] **Step 1: Написать скетч**

```cpp
/*
 * femus RadioBleBridge — phone node of the radio messenger.
 * iPhone (BLE, HM-10) <-> 433 MHz ASK radio (FS1000A + MX-RM-5V).
 * No Firmata here: this node is a dumb line-oriented bridge.
 */

#include <RH_ASK.h>
#include <SoftwareSerial.h>

#define NODE_ADDRESS 2
#define PEER_ADDRESS 1

SoftwareSerial ble(7, 8);      // D7 <- HM-10 TXD, D8 -> HM-10 RXD (via 1k/2k divider)
RH_ASK radio(2000, 11, 12);    // rxPin D11 (MX-RM-5V DATA), txPin D12 (FS1000A DATA)

char lineBuf[51];
byte lineLen = 0;

void setup()
{
  ble.begin(9600);
  radio.init();
  radio.setThisAddress(NODE_ADDRESS);
}

void loop()
{
  while (ble.available()) {
    char c = ble.read();
    if (c == '\n' || c == '\r') {
      if (lineLen > 0) {
        radio.setHeaderFrom(NODE_ADDRESS);
        radio.setHeaderTo(PEER_ADDRESS);
        radio.send((uint8_t *) lineBuf, lineLen);
        radio.waitPacketSent();
        lineLen = 0;
      }
    } else if (lineLen < sizeof(lineBuf) - 1) {
      lineBuf[lineLen++] = c;
    }
  }

  uint8_t buf[RH_ASK_MAX_MESSAGE_LEN];
  uint8_t len = sizeof(buf);
  if (radio.recv(buf, &len)) {
    ble.write(buf, len);
    ble.println();
  }
}
```

- [ ] **Step 2: Компиляция** `arduino-cli compile --fqbn arduino:avr:nano:cpu=atmega328old firmware/RadioBleBridge` (байты в отчёт)
- [ ] **Step 3: docs/devices/radio-ble-bridge.md** — схема узла B: Nano#2 + HM-10 (VCC→5V у 5V-толерантных плат / иначе 3.3V, GND, TXD→D7 напрямую, RXD→D8 через делитель 1к/2к) + FS1000A (DATA→D12, VCC→5V, GND) + MX-RM-5V (DATA→D11, VCC→5V, GND), антенны 17.3 см на обоих радио; питание от USB-павербанка; note: HM-10 default 9600, имя BLE «HM-10»/«BT05», подключение из iOS через CoreBluetooth (сервис FFE0, характеристика FFE1) — для будущего SwiftUI-терминала
- [ ] **Step 4: `composer test` — зелёный**
- [ ] **Step 5: Commit** `feat: RadioBleBridge sketch — phone node for the radio messenger`

---

### Task 5: Пример-чат, схемы, README, чеклист

**Files:**
- Create: `examples/radio-chat.php`, `docs/devices/radio-433.md`
- Modify: `README.md` (Status + Radio messenger упоминание), `docs/hardware-runs.md` (чеклист релиза)

- [ ] **Step 1: examples/radio-chat.php** (STDIN в event loop!)

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;
use Femus\Contracts\RadioLink;
use Femus\Contracts\RadioMessage;

// Usage: php examples/radio-chat.php [port] [my-address] [peer-address]
$board = Board::firmata($argv[1] ?? null);
$address = (int) ($argv[2] ?? 1);
$peer = (int) ($argv[3] ?? RadioLink::BROADCAST);

$radio = $board->radioLink($address);

$radio->onMessage(function (RadioMessage $m) {
    printf("\r[node %d] %s\n> ", $m->from, $m->message);
});

stream_set_blocking(STDIN, false);
$board->loop()->addReadStream(STDIN, function () use ($radio, $peer) {
    $line = trim((string) fgets(STDIN));
    if ($line !== '') {
        $radio->send($peer, $line);
        echo "(sent)\n> ";
    }
});

printf("Radio chat — node %d, sending to %s. Type a message and press Enter.\n> ",
    $address, $peer === RadioLink::BROADCAST ? 'broadcast' : "node {$peer}");
$board->run();
```

- [ ] **Step 2: docs/devices/radio-433.md** — узел A (станция): FS1000A DATA→D12 VCC→5V GND→GND; MX-RM-5V DATA→D11 VCC→5V GND→GND; **антенна: 17.3 см провода вертикально в ANT обоих модулей — без неё дальность < 1 м**; прошивка FemusFirmata (или FemusRadioFirmata по итогам Task 3); запуск `php examples/radio-chat.php <port> 1 2`; тест двух станций (два Mac-терминала = два узла на разных портах, если плат две) и связка с узлом B из radio-ble-bridge.md; типовые ошибки (нет антенн, TX/RX перепутаны местами, D11/D12 заняты LCD — снять LCD-провода)
- [ ] **Step 3: README.md** — Status: `433 MHz radio (RadioLink — addressed packets, CRC)`; убрать радио из roadmap; docs/hardware-runs.md — релиз `2026-08-04-radio`: чеклист (1. прошить узлы, 2. антенны, 3. radio-chat между двумя узлами / приём с bridge-узла, 4. записать)
- [ ] **Step 4: `php -l examples/radio-chat.php` + `composer test` — зелёные**
- [ ] **Step 5: Commit** `docs: radio chat example, wiring guides and hardware checklist`
