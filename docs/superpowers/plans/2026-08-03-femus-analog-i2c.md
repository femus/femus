# femus Analog + I2C (Firmata) — Implementation Plan (План 3 из 6)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Аналоговые входы и шина I2C поверх Firmata-адаптера: контракты `AnalogPin`/`I2cBus`, расширение протокола (analog messaging, I2C sysex), Fake-реализации и три устройства — AnalogSensor, Lcd1602 (PCF8574), Mpu6050. Всё верифицируется байтовыми фикстурами без железа.

**Architecture:** Продолжение слоёв плана 1: новые контракты в `Femus\Contracts`, протокольные байты в `Femus\Adapter\Firmata`, тестовые дублёры в `Femus\Adapter\Fake`, драйверы в `Femus\Device`. Спека: `docs/superpowers/specs/2026-08-02-femus-design.md`. База: код плана 1 в main (43 теста зелёные).

**Tech Stack:** PHP 8.2+, Pest v3, без runtime-зависимостей. Работаем в `main` (без веток — решение владельца).

## Global Constraints

- Каждый PHP-файл начинается с `<?php` + `declare(strict_types=1);` (в сниппетах опущено — ставить всегда)
- Классы `final` (кроме `abstract`), интерфейсы в `Femus\Contracts`
- Никаких runtime-зависимостей кроме `php`; тесты Pest, `composer test`
- Коммиты от sanchescom (git config настроен), сообщения на русском, БЕЗ упоминаний Claude и БЕЗ Co-Authored-By
- Существующие публичные сигнатуры плана 1 не менять (DigitalPin, Loop, Transport, FirmataParser::push и слушатели digital/version остаются как есть; parser расширяется добавлением, не изменением)
- Firmata-константы: ANALOG_MESSAGE=0xE0, REPORT_ANALOG=0xC0, SYSEX I2C_REQUEST=0x76, I2C_REPLY=0x77, I2C_CONFIG=0x78
- Аналоговое значение Firmata — 10 бит (0–1023), передаётся парой 7-битных байтов LSB, MSB

---

### Task 1: Контракт AnalogPin + Fake-реализация

**Files:**
- Create: `src/Contracts/AnalogPin.php`, `src/Adapter/Fake/FakeAnalogPin.php`
- Modify: `src/Contracts/BoardInterface.php` (добавить `analogPin`), `src/Adapter/Fake/FakeBoard.php` (реализация + simulate/schedule)
- Test: `tests/Adapter/Fake/FakeAnalogPinTest.php`

**Interfaces:**
- Consumes: `Femus\Runtime\Loop` (Task 2 плана 1), `Femus\AbstractBoard`
- Produces:
  - `interface Femus\Contracts\AnalogPin` — `channel(): int`, `read(): float` (нормализовано 0.0–1.0), `readRaw(): int` (0–1023), `onChange(callable $listener): void` (листенер `fn(float $normalized): void`)
  - `BoardInterface::analogPin(int $channel): AnalogPin` (добавляется в интерфейс; FirmataBoard реализует в Task 3)
  - `FakeAnalogPin` — плюс `simulate(int $raw): void` (обновляет значение, зовёт листенеры только при изменении)
  - `FakeBoard` — плюс `analogPin(int $channel): AnalogPin`, `fakeAnalogPin(int $channel): FakeAnalogPin`, `simulateAnalog(int $channel, int $raw): void`, `scheduleAnalog(float $delaySeconds, int $channel, int $raw): void`
  - ВНИМАНИЕ: `FirmataBoard` после этой задачи временно не компилируется как реализация интерфейса? Нет — PHP проверяет интерфейс при загрузке класса; чтобы не сломать существующие тесты, в этой задаче добавить в `FirmataBoard` заглушку `analogPin()` бросающую `BoardException('Analog появится в Task 3')` — Task 3 её заменит

- [ ] **Step 1: Написать падающие тесты**

`tests/Adapter/Fake/FakeAnalogPinTest.php`:
```php
use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

it('read нормализует 10-битное значение', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->analogPin(0);
    $board->simulateAnalog(0, 1023);
    expect($pin->readRaw())->toBe(1023)
        ->and($pin->read())->toEqualWithDelta(1.0, 0.001);
    $board->simulateAnalog(0, 512);
    expect($pin->read())->toEqualWithDelta(0.5005, 0.001);
});

it('onChange зовётся только при изменении значения', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->analogPin(2);
    $seen = [];
    $pin->onChange(function (float $v) use (&$seen) { $seen[] = $v; });
    $board->simulateAnalog(2, 100);
    $board->simulateAnalog(2, 100); // без изменения — не событие
    $board->simulateAnalog(2, 200);
    expect($seen)->toHaveCount(2);
});

it('scheduleAnalog инжектит значение через event loop', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->analogPin(1);
    $board->scheduleAnalog(0.01, 1, 300);
    $board->loop()->addTimer(0.05, fn () => $board->stop());
    $board->run();
    expect($pin->readRaw())->toBe(300);
});

it('канал возвращается и пин кэшируется', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->analogPin(3);
    expect($pin->channel())->toBe(3)
        ->and($board->analogPin(3))->toBe($pin);
});
```

- [ ] **Step 2: Убедиться, что тесты падают**

Run: `./vendor/bin/pest tests/Adapter/Fake/FakeAnalogPinTest.php`
Expected: FAIL — `Call to undefined method Femus\Adapter\Fake\FakeBoard::analogPin()`

- [ ] **Step 3: Реализовать**

`src/Contracts/AnalogPin.php`:
```php
namespace Femus\Contracts;

interface AnalogPin
{
    public function channel(): int;

    /** Нормализованное значение 0.0–1.0. */
    public function read(): float;

    /** Сырое 10-битное значение 0–1023. */
    public function readRaw(): int;

    /** @param callable(float): void $listener получает нормализованное значение */
    public function onChange(callable $listener): void;
}
```

В `src/Contracts/BoardInterface.php` добавить метод (после `digitalPin`):
```php
    public function analogPin(int $channel): AnalogPin;
```

`src/Adapter/Fake/FakeAnalogPin.php`:
```php
namespace Femus\Adapter\Fake;

use Femus\Contracts\AnalogPin;

final class FakeAnalogPin implements AnalogPin
{
    private int $raw = 0;

    /** @var list<callable> */
    private array $listeners = [];

    public function __construct(private readonly int $channel)
    {
    }

    public function channel(): int
    {
        return $this->channel;
    }

    public function read(): float
    {
        return $this->raw / 1023.0;
    }

    public function readRaw(): int
    {
        return $this->raw;
    }

    public function onChange(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    /** Тестовый вход: имитация нового отсчёта АЦП. */
    public function simulate(int $raw): void
    {
        if ($raw === $this->raw) {
            return;
        }
        $this->raw = $raw;
        foreach ($this->listeners as $listener) {
            $listener($this->read());
        }
    }
}
```

В `src/Adapter/Fake/FakeBoard.php` добавить (импорт `Femus\Contracts\AnalogPin`):
```php
    /** @var array<int, FakeAnalogPin> */
    private array $analogPins = [];

    public function analogPin(int $channel): AnalogPin
    {
        return $this->analogPins[$channel] ??= new FakeAnalogPin($channel);
    }

    public function fakeAnalogPin(int $channel): FakeAnalogPin
    {
        return $this->analogPins[$channel]
            ?? throw new \LogicException("Аналоговый канал {$channel} ещё не запрошен");
    }

    public function simulateAnalog(int $channel, int $raw): void
    {
        $this->fakeAnalogPin($channel)->simulate($raw);
    }

    public function scheduleAnalog(float $delaySeconds, int $channel, int $raw): void
    {
        $this->loop->addTimer($delaySeconds, fn () => $this->simulateAnalog($channel, $raw));
    }
```

В `src/Adapter/Firmata/FirmataBoard.php` добавить временную заглушку (импорт `Femus\Contracts\AnalogPin`):
```php
    public function analogPin(int $channel): AnalogPin
    {
        throw new BoardException('Аналоговые пины Firmata реализуются в следующей задаче');
    }
```

- [ ] **Step 4: Прогнать все тесты**

Run: `composer test`
Expected: PASS (все, включая 4 новых)

- [ ] **Step 5: Commit**

```bash
git add src/Contracts src/Adapter tests/Adapter/Fake/FakeAnalogPinTest.php
git commit -m "feat: контракт AnalogPin и Fake-реализация"
```

---

### Task 2: Протокол Firmata — analog messaging

**Files:**
- Modify: `src/Adapter/Firmata/Firmata.php` (константы), `src/Adapter/Firmata/FirmataEncoder.php` (reportAnalogChannel), `src/Adapter/Firmata/FirmataParser.php` (onAnalogMessage)
- Test: `tests/Adapter/Firmata/FirmataAnalogProtocolTest.php`

**Interfaces:**
- Consumes: существующие Firmata/FirmataEncoder/FirmataParser (план 1)
- Produces:
  - Константы `Firmata::ANALOG_MESSAGE = 0xE0`, `Firmata::REPORT_ANALOG = 0xC0`
  - `FirmataEncoder::reportAnalogChannel(int $channel, bool $enable): string`
  - `FirmataParser::onAnalogMessage(callable $fn): void` — листенер `fn(int $channel, int $value)`; value = LSB | (MSB << 7), 0–1023
  - Существующее поведение push()/digital/version/sysex не меняется

- [ ] **Step 1: Написать падающие тесты**

`tests/Adapter/Firmata/FirmataAnalogProtocolTest.php`:
```php
use Femus\Adapter\Firmata\Firmata;
use Femus\Adapter\Firmata\FirmataEncoder;
use Femus\Adapter\Firmata\FirmataParser;

it('кодирует включение репортинга аналогового канала', function () {
    expect(FirmataEncoder::reportAnalogChannel(0, true))->toBe("\xC0\x01")
        ->and(FirmataEncoder::reportAnalogChannel(5, false))->toBe("\xC5\x00");
});

it('парсит analog message: канал 2, значение 1023', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onAnalogMessage(function (int $ch, int $value) use (&$got) {
        $got = [$ch, $value];
    });
    $parser->push("\xE2\x7F\x07"); // 0x7F | (0x07 << 7) = 1023
    expect($got)->toBe([2, 1023]);
});

it('парсит analog message, разрезанное между push', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onAnalogMessage(function (int $ch, int $value) use (&$got) {
        $got = [$ch, $value];
    });
    $parser->push("\xE0");
    $parser->push("\x40\x01"); // 0x40 | (0x01<<7) = 192
    expect($got)->toBe([0, 192]);
});

it('digital и analog сообщения в одном потоке не мешают друг другу', function () {
    $parser = new FirmataParser();
    $events = [];
    $parser->onDigitalMessage(function (int $port, int $mask) use (&$events) {
        $events[] = ['d', $port, $mask];
    });
    $parser->onAnalogMessage(function (int $ch, int $value) use (&$events) {
        $events[] = ['a', $ch, $value];
    });
    $parser->push("\x90\x04\x00" . "\xE1\x0A\x00");
    expect($events)->toBe([['d', 0, 4], ['a', 1, 10]]);
});
```

- [ ] **Step 2: Убедиться, что тесты падают**

Run: `./vendor/bin/pest tests/Adapter/Firmata/FirmataAnalogProtocolTest.php`
Expected: FAIL — `Call to undefined method ... reportAnalogChannel()`

- [ ] **Step 3: Реализовать**

В `src/Adapter/Firmata/Firmata.php` добавить константы:
```php
    public const ANALOG_MESSAGE = 0xE0; // | канал (0-15)
    public const REPORT_ANALOG = 0xC0;  // | канал
```

В `src/Adapter/Firmata/FirmataEncoder.php` добавить:
```php
    public static function reportAnalogChannel(int $channel, bool $enable): string
    {
        return chr(Firmata::REPORT_ANALOG | $channel) . chr($enable ? 1 : 0);
    }
```

В `src/Adapter/Firmata/FirmataParser.php`: добавить поле и метод-регистратор:
```php
    /** @var list<callable> */
    private array $analogListeners = [];

    public function onAnalogMessage(callable $fn): void
    {
        $this->analogListeners[] = $fn;
    }
```
и в `push()` добавить ветку ПЕРЕД веткой неизвестного байта (после ветки REPORT_VERSION):
```php
            } elseif (($first & 0xF0) === Firmata::ANALOG_MESSAGE) {
                if ($length < 3) {
                    return;
                }
                $channel = $first & 0x0F;
                $value = ord($this->buffer[1]) | (ord($this->buffer[2]) << 7);
                $this->buffer = substr($this->buffer, 3);
                foreach ($this->analogListeners as $listener) {
                    $listener($channel, $value);
                }
```

- [ ] **Step 4: Прогнать все тесты**

Run: `composer test`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Adapter/Firmata tests/Adapter/Firmata/FirmataAnalogProtocolTest.php
git commit -m "feat: протокол Firmata — analog messaging (0xE0/0xC0)"
```

---

### Task 3: FirmataAnalogPin + подключение к FirmataBoard

**Files:**
- Create: `src/Adapter/Firmata/FirmataAnalogPin.php`
- Modify: `src/Adapter/Firmata/FirmataBoard.php` (заменить заглушку analogPin, обработчик analog message)
- Test: `tests/Adapter/Firmata/FirmataAnalogPinTest.php`

**Interfaces:**
- Consumes: `AnalogPin` (Task 1), `FirmataEncoder::reportAnalogChannel`, `FirmataParser::onAnalogMessage` (Task 2), `InMemoryTransport`, helper `readyBoard()` уже существует в FirmataBoardTest — в новом тест-файле объявить свой helper `readyAnalogBoard()` (Pest-функции глобальны, имя должно отличаться)
- Produces:
  - `final class FirmataAnalogPin implements AnalogPin` — `@internal updateFromBoard(int $raw): void` (листенеры только при изменении)
  - `FirmataBoard::analogPin(int $channel): AnalogPin` — при первом запросе шлёт `reportAnalogChannel($channel, true)`, кэширует пин; входящие analog message обновляют пин

- [ ] **Step 1: Написать падающие тесты**

`tests/Adapter/Firmata/FirmataAnalogPinTest.php`:
```php
use Femus\Adapter\Firmata\FirmataBoard;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\InMemoryTransport;

function readyAnalogBoard(InMemoryTransport $transport): FirmataBoard
{
    $board = new FirmataBoard($transport, new StreamSelectLoop());
    $transport->feed("\xF9\x02\x05");
    $board->awaitReady();

    return $board;
}

it('запрос аналогового пина включает репортинг канала', function () {
    $transport = new InMemoryTransport();
    $board = readyAnalogBoard($transport);
    $transport->written = '';

    $board->analogPin(3);

    expect($transport->written)->toBe("\xC3\x01");
});

it('входящее analog message обновляет пин и зовёт onChange', function () {
    $transport = new InMemoryTransport();
    $board = readyAnalogBoard($transport);
    $pin = $board->analogPin(0);

    $seen = null;
    $pin->onChange(function (float $v) use (&$seen, $board) {
        $seen = $v;
        $board->stop();
    });

    $transport->feed("\xE0\x7F\x07"); // 1023
    $board->loop()->addTimer(1.0, fn () => $board->stop());
    $board->run();

    expect($pin->readRaw())->toBe(1023)
        ->and($seen)->toEqualWithDelta(1.0, 0.001);
});

it('сообщение чужого канала не трогает пин', function () {
    $transport = new InMemoryTransport();
    $board = readyAnalogBoard($transport);
    $pin = $board->analogPin(0);

    $transport->feed("\xE5\x0A\x00"); // канал 5
    $board->loop()->addTimer(0.05, fn () => $board->stop());
    $board->run();

    expect($pin->readRaw())->toBe(0);
});
```

- [ ] **Step 2: Убедиться, что тесты падают**

Run: `./vendor/bin/pest tests/Adapter/Firmata/FirmataAnalogPinTest.php`
Expected: FAIL — BoardException «Аналоговые пины Firmata реализуются в следующей задаче»

- [ ] **Step 3: Реализовать**

`src/Adapter/Firmata/FirmataAnalogPin.php`:
```php
namespace Femus\Adapter\Firmata;

use Femus\Contracts\AnalogPin;

final class FirmataAnalogPin implements AnalogPin
{
    private int $raw = 0;

    /** @var list<callable> */
    private array $listeners = [];

    public function __construct(private readonly int $channel)
    {
    }

    public function channel(): int
    {
        return $this->channel;
    }

    public function read(): float
    {
        return $this->raw / 1023.0;
    }

    public function readRaw(): int
    {
        return $this->raw;
    }

    public function onChange(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    /** @internal вызывается FirmataBoard при входящем analog message */
    public function updateFromBoard(int $raw): void
    {
        if ($raw === $this->raw) {
            return;
        }
        $this->raw = $raw;
        foreach ($this->listeners as $listener) {
            $listener($this->read());
        }
    }
}
```

В `src/Adapter/Firmata/FirmataBoard.php`:
- поле `/** @var array<int, FirmataAnalogPin> */ private array $analogPins = [];`
- в конструкторе после `onDigitalMessage`: `$this->parser->onAnalogMessage($this->handleAnalogMessage(...));`
- заменить заглушку:
```php
    public function analogPin(int $channel): AnalogPin
    {
        if (!isset($this->analogPins[$channel])) {
            $this->transport->write(FirmataEncoder::reportAnalogChannel($channel, true));
            $this->analogPins[$channel] = new FirmataAnalogPin($channel);
        }

        return $this->analogPins[$channel];
    }

    private function handleAnalogMessage(int $channel, int $value): void
    {
        if (isset($this->analogPins[$channel])) {
            $this->analogPins[$channel]->updateFromBoard($value);
        }
    }
```

- [ ] **Step 4: Прогнать все тесты**

Run: `composer test`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Adapter/Firmata tests/Adapter/Firmata/FirmataAnalogPinTest.php
git commit -m "feat: аналоговые пины FirmataBoard"
```

---

### Task 4: Устройство AnalogSensor

**Files:**
- Create: `src/Device/AnalogSensor.php`
- Modify: `src/AbstractBoard.php` (фабрика `analogSensor`)
- Test: `tests/Device/AnalogSensorTest.php`

**Interfaces:**
- Consumes: `AnalogPin`, `Loop` (tick), `FakeBoard::simulateAnalog/scheduleAnalog`
- Produces:
  - `final class Femus\Device\AnalogSensor` — `__construct(AnalogPin $pin, Loop $loop, float $threshold = 0.01)`; `read(): float`, `readRaw(): int`, `onChange(callable $fn): void` (fn(float), зовётся только если |новое − последнее сообщённое| ≥ threshold), `waitForValueAbove(float $level, ?float $timeoutSeconds = null): bool` (блокирующий, tick(0.05), false по таймауту)
  - `AbstractBoard::analogSensor(int $channel, float $threshold = 0.01): AnalogSensor`

- [ ] **Step 1: Написать падающие тесты**

`tests/Device/AnalogSensorTest.php`:
```php
use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

it('read проксирует пин', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sensor = $board->analogSensor(0);
    $board->simulateAnalog(0, 512);
    expect($sensor->readRaw())->toBe(512)
        ->and($sensor->read())->toEqualWithDelta(0.5005, 0.001);
});

it('onChange фильтрует шум ниже порога', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sensor = $board->analogSensor(0, threshold: 0.05);
    $seen = [];
    $sensor->onChange(function (float $v) use (&$seen) { $seen[] = $v; });
    $board->simulateAnalog(0, 100);  // 0.098 — скачок от 0 больше порога → событие
    $board->simulateAnalog(0, 110);  // +0.0098 — меньше порога → тишина
    $board->simulateAnalog(0, 200);  // от последнего СООБЩЁННОГО (100) — больше порога → событие
    expect($seen)->toHaveCount(2);
});

it('waitForValueAbove ловит запланированное значение', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sensor = $board->analogSensor(1);
    $board->scheduleAnalog(0.02, 1, 900);
    expect($sensor->waitForValueAbove(0.8, timeoutSeconds: 1.0))->toBeTrue();
});

it('waitForValueAbove возвращает false по таймауту', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $sensor = $board->analogSensor(1);
    expect($sensor->waitForValueAbove(0.8, timeoutSeconds: 0.05))->toBeFalse();
});
```

- [ ] **Step 2: Убедиться, что тесты падают**

Run: `./vendor/bin/pest tests/Device/AnalogSensorTest.php`
Expected: FAIL — `Call to undefined method Femus\Adapter\Fake\FakeBoard::analogSensor()`

- [ ] **Step 3: Реализовать**

`src/Device/AnalogSensor.php`:
```php
namespace Femus\Device;

use Femus\Contracts\AnalogPin;
use Femus\Runtime\Loop;

final class AnalogSensor
{
    /** @var list<callable> */
    private array $listeners = [];

    private float $lastReported = 0.0;

    public function __construct(
        private readonly AnalogPin $pin,
        private readonly Loop $loop,
        private readonly float $threshold = 0.01,
    ) {
        $pin->onChange(function (float $value): void {
            if (abs($value - $this->lastReported) < $this->threshold) {
                return;
            }
            $this->lastReported = $value;
            foreach ($this->listeners as $listener) {
                $listener($value);
            }
        });
    }

    public function read(): float
    {
        return $this->pin->read();
    }

    public function readRaw(): int
    {
        return $this->pin->readRaw();
    }

    public function onChange(callable $fn): void
    {
        $this->listeners[] = $fn;
    }

    public function waitForValueAbove(float $level, ?float $timeoutSeconds = null): bool
    {
        $deadline = $timeoutSeconds === null ? null : hrtime(true) / 1e9 + $timeoutSeconds;
        while ($this->pin->read() < $level) {
            if ($deadline !== null && hrtime(true) / 1e9 >= $deadline) {
                return false;
            }
            $this->loop->tick(0.05);
        }

        return true;
    }
}
```

В `src/AbstractBoard.php` добавить (импорт `Femus\Device\AnalogSensor`):
```php
    public function analogSensor(int $channel, float $threshold = 0.01): AnalogSensor
    {
        return new AnalogSensor($this->analogPin($channel), $this->loop, $threshold);
    }
```

- [ ] **Step 4: Прогнать все тесты**

Run: `composer test`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Device/AnalogSensor.php src/AbstractBoard.php tests/Device/AnalogSensorTest.php
git commit -m "feat: устройство AnalogSensor с порогом шума"
```

---

### Task 5: Протокол Firmata — sysex-диспетчеризация и I2C-байты

**Files:**
- Modify: `src/Adapter/Firmata/Firmata.php` (I2C-константы), `src/Adapter/Firmata/FirmataParser.php` (onSysex), `src/Adapter/Firmata/FirmataEncoder.php` (i2c-методы)
- Create: `src/Adapter/Firmata/I2cReply.php`
- Test: `tests/Adapter/Firmata/FirmataI2cProtocolTest.php`

**Interfaces:**
- Consumes: существующий парсер (sysex сейчас пропускается — расширить, не сломав)
- Produces:
  - Константы: `Firmata::I2C_REQUEST = 0x76`, `Firmata::I2C_REPLY = 0x77`, `Firmata::I2C_CONFIG = 0x78`
  - `FirmataParser::onSysex(callable $fn): void` — листенер `fn(string $payload)`, payload = байты МЕЖДУ 0xF0 и 0xF7 (включая командный байт)
  - `FirmataEncoder::i2cConfig(int $delayMicros = 0): string`
  - `FirmataEncoder::i2cWrite(int $address, string $bytes): string` — F0 76 addr 0x00 (данные парами LSB/MSB) F7
  - `FirmataEncoder::i2cReadRegister(int $address, int $register, int $length): string` — F0 76 addr 0x08 (регистр парой) (длина парой) F7
  - `final class I2cReply` — `public readonly int $address`, `public readonly int $register`, `public readonly string $data`; `static fromSysexPayload(string $payload): ?I2cReply` (null если payload не I2C_REPLY)

- [ ] **Step 1: Написать падающие тесты**

`tests/Adapter/Firmata/FirmataI2cProtocolTest.php`:
```php
use Femus\Adapter\Firmata\FirmataEncoder;
use Femus\Adapter\Firmata\FirmataParser;
use Femus\Adapter\Firmata\I2cReply;

it('кодирует i2c config', function () {
    expect(FirmataEncoder::i2cConfig())->toBe("\xF0\x78\x00\x00\xF7");
});

it('кодирует i2c write: адрес 0x27, один байт 0x9D', function () {
    // 0x9D > 0x7F → пара LSB=0x1D, MSB=0x01
    expect(FirmataEncoder::i2cWrite(0x27, "\x9D"))->toBe("\xF0\x76\x27\x00\x1D\x01\xF7");
});

it('кодирует i2c read: адрес 0x68, регистр 0x3B, 6 байт', function () {
    expect(FirmataEncoder::i2cReadRegister(0x68, 0x3B, 6))
        ->toBe("\xF0\x76\x68\x08\x3B\x00\x06\x00\xF7");
});

it('onSysex отдаёт payload между F0 и F7', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onSysex(function (string $payload) use (&$got) { $got = $payload; });
    $parser->push("\xF0\x79\x02\x05\xF7");
    expect($got)->toBe("\x79\x02\x05");
});

it('sysex, разрезанный между push, собирается целиком', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onSysex(function (string $payload) use (&$got) { $got = $payload; });
    $parser->push("\xF0\x77\x68");
    expect($got)->toBeNull();
    $parser->push("\x00\xF7");
    expect($got)->toBe("\x77\x68\x00");
});

it('декодирует i2c reply: адрес 0x68, регистр 0x3B, данные 0x40 0x00', function () {
    // payload: 77, addr LSB/MSB, reg LSB/MSB, данные парами
    $reply = I2cReply::fromSysexPayload("\x77\x68\x00\x3B\x00\x40\x00\x00\x00");
    expect($reply)->not->toBeNull()
        ->and($reply->address)->toBe(0x68)
        ->and($reply->register)->toBe(0x3B)
        ->and($reply->data)->toBe("\x40\x00");
});

it('декодирует данные reply с байтом больше 0x7F', function () {
    // байт 0x9D → пара 0x1D 0x01
    $reply = I2cReply::fromSysexPayload("\x77\x27\x00\x00\x00\x1D\x01");
    expect($reply->data)->toBe("\x9D");
});

it('fromSysexPayload возвращает null для не-I2C sysex', function () {
    expect(I2cReply::fromSysexPayload("\x79\x02\x05"))->toBeNull();
});
```

- [ ] **Step 2: Убедиться, что тесты падают**

Run: `./vendor/bin/pest tests/Adapter/Firmata/FirmataI2cProtocolTest.php`
Expected: FAIL — `Call to undefined method ... i2cConfig()`

- [ ] **Step 3: Реализовать**

В `src/Adapter/Firmata/Firmata.php` добавить:
```php
    public const I2C_REQUEST = 0x76;
    public const I2C_REPLY = 0x77;
    public const I2C_CONFIG = 0x78;
    public const I2C_MODE_WRITE = 0x00;
    public const I2C_MODE_READ_ONCE = 0x08;
```

В `src/Adapter/Firmata/FirmataEncoder.php` добавить:
```php
    public static function i2cConfig(int $delayMicros = 0): string
    {
        return chr(Firmata::SYSEX_START) . chr(Firmata::I2C_CONFIG)
            . chr($delayMicros & 0x7F) . chr(($delayMicros >> 7) & 0x7F)
            . chr(Firmata::SYSEX_END);
    }

    public static function i2cWrite(int $address, string $bytes): string
    {
        return chr(Firmata::SYSEX_START) . chr(Firmata::I2C_REQUEST)
            . chr($address) . chr(Firmata::I2C_MODE_WRITE)
            . self::encode7bitPairs($bytes)
            . chr(Firmata::SYSEX_END);
    }

    public static function i2cReadRegister(int $address, int $register, int $length): string
    {
        return chr(Firmata::SYSEX_START) . chr(Firmata::I2C_REQUEST)
            . chr($address) . chr(Firmata::I2C_MODE_READ_ONCE)
            . chr($register & 0x7F) . chr(($register >> 7) & 0x7F)
            . chr($length & 0x7F) . chr(($length >> 7) & 0x7F)
            . chr(Firmata::SYSEX_END);
    }

    /** Каждый байт → пара 7-битных: LSB, затем старший бит. */
    private static function encode7bitPairs(string $bytes): string
    {
        $out = '';
        foreach (str_split($bytes) as $byte) {
            $value = ord($byte);
            $out .= chr($value & 0x7F) . chr(($value >> 7) & 0x7F);
        }

        return $out;
    }
```

В `src/Adapter/Firmata/FirmataParser.php`: поле `private array $sysexListeners = [];` (с `@var list<callable>`), метод:
```php
    public function onSysex(callable $fn): void
    {
        $this->sysexListeners[] = $fn;
    }
```
и в ветке SYSEX_START заменить «скип» на диспетчеризацию:
```php
            } elseif ($first === Firmata::SYSEX_START) {
                $end = strpos($this->buffer, chr(Firmata::SYSEX_END));
                if ($end === false) {
                    return; // sysex ещё не пришёл целиком
                }
                $payload = substr($this->buffer, 1, $end - 1);
                $this->buffer = substr($this->buffer, $end + 1);
                foreach ($this->sysexListeners as $listener) {
                    $listener($payload);
                }
```

`src/Adapter/Firmata/I2cReply.php`:
```php
namespace Femus\Adapter\Firmata;

final class I2cReply
{
    public function __construct(
        public readonly int $address,
        public readonly int $register,
        public readonly string $data,
    ) {
    }

    /** Разбирает payload sysex-фрейма; null, если это не I2C_REPLY. */
    public static function fromSysexPayload(string $payload): ?self
    {
        if ($payload === '' || ord($payload[0]) !== Firmata::I2C_REPLY || strlen($payload) < 5) {
            return null;
        }
        $address = ord($payload[1]) | (ord($payload[2]) << 7);
        $register = ord($payload[3]) | (ord($payload[4]) << 7);
        $data = '';
        for ($i = 5; $i + 1 < strlen($payload); $i += 2) {
            $data .= chr(ord($payload[$i]) | (ord($payload[$i + 1]) << 7));
        }

        return new self($address, $register, $data);
    }
}
```

- [ ] **Step 4: Прогнать все тесты**

Run: `composer test`
Expected: PASS (в т.ч. старый тест «пропускает sysex-блоки» — он проверяет, что digital после sysex парсится, диспетчеризация его не ломает)

- [ ] **Step 5: Commit**

```bash
git add src/Adapter/Firmata tests/Adapter/Firmata/FirmataI2cProtocolTest.php
git commit -m "feat: протокол Firmata — sysex-диспетчеризация и кодек I2C"
```

---

### Task 6: Контракт I2cBus, FakeI2cBus и FirmataI2cBus

**Files:**
- Create: `src/Contracts/I2cBus.php`, `src/Contracts/I2cException.php`, `src/Adapter/Fake/FakeI2cBus.php`, `src/Adapter/Firmata/FirmataI2cBus.php`
- Modify: `src/Contracts/BoardInterface.php` (метод `i2c()`), `src/Adapter/Fake/FakeBoard.php`, `src/Adapter/Firmata/FirmataBoard.php`
- Test: `tests/Adapter/Fake/FakeI2cBusTest.php`, `tests/Adapter/Firmata/FirmataI2cBusTest.php`

**Interfaces:**
- Consumes: Task 5 (кодек, onSysex, I2cReply), `Loop::tick`
- Produces:
  - `interface Femus\Contracts\I2cBus` — `write(int $address, string $bytes): void`, `readRegister(int $address, int $register, int $length): string` (блокирующий; бросает `I2cException` по таймауту)
  - `final class Femus\Contracts\I2cException extends \RuntimeException`
  - `BoardInterface::i2c(): I2cBus`
  - `FakeI2cBus` — плюс `public array $writes` (list of `[адрес, байты]`), `queueRead(string $bytes): void` (очередь ответов для readRegister; пустая очередь → I2cException)
  - `FirmataI2cBus::__construct(Transport $transport, FirmataParser $parser, Loop $loop, float $timeout = 1.0)` — конструктор шлёт i2cConfig(); readRegister шлёт запрос и крутит tick до ответа с совпадающими address+register
  - `FakeBoard::i2c(): I2cBus` (кэшированный FakeI2cBus, есть акцессор `fakeI2c(): FakeI2cBus`), `FirmataBoard::i2c(): I2cBus` (лениво создаёт FirmataI2cBus)

- [ ] **Step 1: Написать падающие тесты**

`tests/Adapter/Fake/FakeI2cBusTest.php`:
```php
use Femus\Adapter\Fake\FakeBoard;
use Femus\Contracts\I2cException;
use Femus\Runtime\StreamSelectLoop;

it('записывает и запоминает байты', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $bus = $board->i2c();
    $bus->write(0x27, "\x0C");
    expect($board->fakeI2c()->writes)->toBe([[0x27, "\x0C"]]);
});

it('readRegister отдаёт из очереди', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->fakeI2c()->queueRead("\x40\x00");
    expect($board->i2c()->readRegister(0x68, 0x3B, 2))->toBe("\x40\x00");
});

it('readRegister с пустой очередью бросает исключение', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->i2c()->readRegister(0x68, 0x3B, 2);
})->throws(I2cException::class);
```

`tests/Adapter/Firmata/FirmataI2cBusTest.php`:
```php
use Femus\Adapter\Firmata\FirmataBoard;
use Femus\Contracts\I2cException;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\InMemoryTransport;

function readyI2cBoard(InMemoryTransport $transport): FirmataBoard
{
    $board = new FirmataBoard($transport, new StreamSelectLoop());
    $transport->feed("\xF9\x02\x05");
    $board->awaitReady();

    return $board;
}

it('первый вызов i2c() шлёт I2C_CONFIG', function () {
    $transport = new InMemoryTransport();
    $board = readyI2cBoard($transport);
    $transport->written = '';

    $board->i2c();

    expect($transport->written)->toBe("\xF0\x78\x00\x00\xF7");
});

it('write кодирует запрос', function () {
    $transport = new InMemoryTransport();
    $board = readyI2cBoard($transport);
    $bus = $board->i2c();
    $transport->written = '';

    $bus->write(0x27, "\x0C");

    expect($transport->written)->toBe("\xF0\x76\x27\x00\x0C\x00\xF7");
});

it('readRegister блокируется до ответа и декодирует данные', function () {
    $transport = new InMemoryTransport();
    $board = readyI2cBoard($transport);
    $bus = $board->i2c();

    // ответ придёт через event loop: планируем feed после старта чтения
    $board->loop()->addTimer(0.01, function () use ($transport) {
        $transport->feed("\xF0\x77\x68\x00\x3B\x00\x40\x00\x00\x00\xF7");
    });

    $data = $bus->readRegister(0x68, 0x3B, 2);

    expect($data)->toBe("\x40\x00");
});

it('readRegister бросает исключение по таймауту', function () {
    $transport = new InMemoryTransport();
    $board = readyI2cBoard($transport);
    $board->i2c()->readRegister(0x68, 0x3B, 2);
})->throws(I2cException::class);
```
Для теста таймаута FirmataI2cBus создаётся внутри FirmataBoard — таймаут по умолчанию 1.0с; тест допускает ожидание до ~1с (это самый долгий тест сьюта, приемлемо).

- [ ] **Step 2: Убедиться, что тесты падают**

Run: `./vendor/bin/pest tests/Adapter/Fake/FakeI2cBusTest.php tests/Adapter/Firmata/FirmataI2cBusTest.php`
Expected: FAIL — `Call to undefined method ... i2c()`

- [ ] **Step 3: Реализовать**

`src/Contracts/I2cBus.php`:
```php
namespace Femus\Contracts;

interface I2cBus
{
    public function write(int $address, string $bytes): void;

    /** Блокирующее чтение $length байт из регистра. @throws I2cException по таймауту */
    public function readRegister(int $address, int $register, int $length): string;
}
```

`src/Contracts/I2cException.php`:
```php
namespace Femus\Contracts;

final class I2cException extends \RuntimeException
{
}
```

В `src/Contracts/BoardInterface.php` добавить:
```php
    public function i2c(): I2cBus;
```

`src/Adapter/Fake/FakeI2cBus.php`:
```php
namespace Femus\Adapter\Fake;

use Femus\Contracts\I2cBus;
use Femus\Contracts\I2cException;

final class FakeI2cBus implements I2cBus
{
    /** @var list<array{0: int, 1: string}> */
    public array $writes = [];

    /** @var list<string> */
    private array $readQueue = [];

    public function write(int $address, string $bytes): void
    {
        $this->writes[] = [$address, $bytes];
    }

    public function readRegister(int $address, int $register, int $length): string
    {
        if ($this->readQueue === []) {
            throw new I2cException(
                sprintf('Нет запланированного ответа для чтения 0x%02X/0x%02X', $address, $register),
            );
        }

        return array_shift($this->readQueue);
    }

    public function queueRead(string $bytes): void
    {
        $this->readQueue[] = $bytes;
    }
}
```

В `src/Adapter/Fake/FakeBoard.php` добавить (импорт `Femus\Contracts\I2cBus`):
```php
    private ?FakeI2cBus $i2c = null;

    public function i2c(): I2cBus
    {
        return $this->i2c ??= new FakeI2cBus();
    }

    public function fakeI2c(): FakeI2cBus
    {
        $bus = $this->i2c();
        assert($bus instanceof FakeI2cBus);

        return $bus;
    }
```

`src/Adapter/Firmata/FirmataI2cBus.php`:
```php
namespace Femus\Adapter\Firmata;

use Femus\Contracts\I2cBus;
use Femus\Contracts\I2cException;
use Femus\Runtime\Loop;
use Femus\Transport\Transport;

final class FirmataI2cBus implements I2cBus
{
    private ?I2cReply $pendingReply = null;

    public function __construct(
        private readonly Transport $transport,
        FirmataParser $parser,
        private readonly Loop $loop,
        private readonly float $timeout = 1.0,
    ) {
        $parser->onSysex(function (string $payload): void {
            $reply = I2cReply::fromSysexPayload($payload);
            if ($reply !== null) {
                $this->pendingReply = $reply;
            }
        });
        $transport->write(FirmataEncoder::i2cConfig());
    }

    public function write(int $address, string $bytes): void
    {
        $this->transport->write(FirmataEncoder::i2cWrite($address, $bytes));
    }

    public function readRegister(int $address, int $register, int $length): string
    {
        $this->pendingReply = null;
        $this->transport->write(FirmataEncoder::i2cReadRegister($address, $register, $length));

        $deadline = hrtime(true) / 1e9 + $this->timeout;
        while (true) {
            $reply = $this->pendingReply;
            if ($reply !== null && $reply->address === $address && $reply->register === $register) {
                return $reply->data;
            }
            $remaining = $deadline - hrtime(true) / 1e9;
            if ($remaining <= 0) {
                throw new I2cException(
                    sprintf('I2C 0x%02X не ответил на чтение регистра 0x%02X (устройство подключено? адрес верный?)', $address, $register),
                );
            }
            $this->loop->tick(min(0.05, $remaining));
        }
    }
}
```

В `src/Adapter/Firmata/FirmataBoard.php` добавить (импорт `Femus\Contracts\I2cBus`):
```php
    private ?FirmataI2cBus $i2c = null;

    public function i2c(): I2cBus
    {
        return $this->i2c ??= new FirmataI2cBus($this->transport, $this->parser, $this->loop);
    }
```
(поле `$this->parser` уже существует как `private readonly FirmataParser`)

- [ ] **Step 4: Прогнать все тесты**

Run: `composer test`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Contracts src/Adapter tests/Adapter
git commit -m "feat: шина I2C — контракт, Fake и Firmata-реализации"
```

---

### Task 7: Драйвер Lcd1602 (PCF8574)

**Files:**
- Create: `src/Device/Lcd1602.php`
- Modify: `src/AbstractBoard.php` (фабрика `lcd1602`)
- Test: `tests/Device/Lcd1602Test.php`

**Interfaces:**
- Consumes: `I2cBus::write` (Task 6), `FakeBoard::i2c()/fakeI2c()`
- Produces:
  - `final class Femus\Device\Lcd1602` — `__construct(I2cBus $bus, int $address = 0x27)` (конструктор выполняет init-последовательность HD44780 в 4-битном режиме); `clear(): void`, `setCursor(int $col, int $row): void`, `write(string $text): void` (ASCII), `backlight(bool $on): void`
  - `AbstractBoard::lcd1602(int $address = 0x27): Lcd1602`
  - Байтовая модель PCF8574: bit0=RS, bit1=RW(всегда 0), bit2=EN, bit3=подсветка, bits4–7=ниббл данных. Каждый байт → два ниббла, каждый ниббл пульсируется EN (байт с EN=1, затем тот же с EN=0)

- [ ] **Step 1: Написать падающие тесты**

`tests/Device/Lcd1602Test.php`:
```php
use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

/** Все байты, ушедшие на адрес, одной строкой. */
function lcdBytes(FakeBoard $board, int $address = 0x27): string
{
    $out = '';
    foreach ($board->fakeI2c()->writes as [$addr, $bytes]) {
        if ($addr === $address) {
            $out .= $bytes;
        }
    }

    return $out;
}

it('инициализация шлёт установку 4-битного режима', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->lcd1602();
    // первый шаг init: команда 0x33 (две посылки ниббла 0x3):
    // ниббл 0x3, RS=0, BL=1: байт 0x38; пульс EN: 0x3C, 0x38
    expect(lcdBytes($board))->toContain("\x3C\x38");
});

it('write выводит символ с установленным RS', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $lcd = $board->lcd1602();
    $board->fakeI2c()->writes = [];

    $lcd->write('A'); // 0x41: старший ниббл 0x4 → 0x49 (RS|BL), пульс 0x4D,0x49; младший 0x1 → 0x19, пульс 0x1D,0x19

    expect(lcdBytes($board))->toBe("\x4D\x49\x1D\x19");
});

it('clear шлёт команду 0x01 без RS', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $lcd = $board->lcd1602();
    $board->fakeI2c()->writes = [];

    $lcd->clear(); // 0x01: старший ниббл 0x0 → 0x08(BL), пульс 0x0C,0x08; младший 0x1 → 0x18, пульс 0x1C,0x18

    expect(lcdBytes($board))->toBe("\x0C\x08\x1C\x18");
});

it('setCursor второй строки использует смещение 0x40', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $lcd = $board->lcd1602();
    $board->fakeI2c()->writes = [];

    $lcd->setCursor(0, 1); // команда 0x80|0x40 = 0xC0: нибблы 0xC и 0x0 → 0xC8 пульс, 0x08 пульс

    expect(lcdBytes($board))->toBe("\xCC\xC8\x0C\x08");
});

it('backlight(false) гасит бит подсветки в последующих командах', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $lcd = $board->lcd1602();
    $lcd->backlight(false);
    $board->fakeI2c()->writes = [];

    $lcd->clear(); // те же байты, но без бита 0x08

    expect(lcdBytes($board))->toBe("\x04\x00\x14\x10");
});
```

- [ ] **Step 2: Убедиться, что тесты падают**

Run: `./vendor/bin/pest tests/Device/Lcd1602Test.php`
Expected: FAIL — `Call to undefined method Femus\Adapter\Fake\FakeBoard::lcd1602()`

- [ ] **Step 3: Реализовать**

`src/Device/Lcd1602.php`:
```php
namespace Femus\Device;

use Femus\Contracts\I2cBus;

/**
 * LCD 16x2 на HD44780 с I2C-бэкпаком PCF8574.
 * Байт PCF8574: bit0=RS, bit1=RW, bit2=EN, bit3=подсветка, bits4-7=ниббл.
 */
final class Lcd1602
{
    private const RS = 0x01;
    private const EN = 0x04;
    private const BACKLIGHT = 0x08;

    private bool $backlightOn = true;

    public function __construct(
        private readonly I2cBus $bus,
        private readonly int $address = 0x27,
    ) {
        // Init HD44780 в 4-битном режиме (даташит, последовательность через 0x33/0x32)
        $this->command(0x33);
        $this->command(0x32);
        $this->command(0x28); // 4 бита, 2 строки, 5x8
        $this->command(0x0C); // дисплей вкл, курсор выкл
        $this->command(0x06); // инкремент курсора
        $this->command(0x01); // очистка
        usleep(2000);         // clear требует >1.52 мс
    }

    public function clear(): void
    {
        $this->command(0x01);
        usleep(2000);
    }

    public function setCursor(int $col, int $row): void
    {
        $this->command(0x80 | ($col + ($row === 0 ? 0x00 : 0x40)));
    }

    public function write(string $text): void
    {
        foreach (str_split($text) as $char) {
            $this->send(ord($char), self::RS);
        }
    }

    public function backlight(bool $on): void
    {
        $this->backlightOn = $on;
    }

    private function command(int $byte): void
    {
        $this->send($byte, 0x00);
    }

    private function send(int $byte, int $flags): void
    {
        $this->pulseNibble(($byte >> 4) & 0x0F, $flags);
        $this->pulseNibble($byte & 0x0F, $flags);
    }

    private function pulseNibble(int $nibble, int $flags): void
    {
        $base = ($nibble << 4) | $flags | ($this->backlightOn ? self::BACKLIGHT : 0x00);
        $this->bus->write($this->address, chr($base | self::EN));
        $this->bus->write($this->address, chr($base));
        usleep(50); // HD44780 требует >37 мкс на команду
    }
}
```

В `src/AbstractBoard.php` добавить (импорт `Femus\Device\Lcd1602`):
```php
    public function lcd1602(int $address = 0x27): Lcd1602
    {
        return new Lcd1602($this->i2c(), $address);
    }
```

- [ ] **Step 4: Прогнать все тесты**

Run: `composer test`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Device/Lcd1602.php src/AbstractBoard.php tests/Device/Lcd1602Test.php
git commit -m "feat: драйвер LCD1602 через PCF8574"
```

---

### Task 8: Драйвер Mpu6050 (GY-521)

**Files:**
- Create: `src/Device/Mpu6050.php`
- Modify: `src/AbstractBoard.php` (фабрика `mpu6050`)
- Test: `tests/Device/Mpu6050Test.php`

**Interfaces:**
- Consumes: `I2cBus` (Task 6), `FakeI2cBus::queueRead`
- Produces:
  - `final class Femus\Device\Mpu6050` — `__construct(I2cBus $bus, int $address = 0x68)` (конструктор будит чип: write регистра 0x6B ← 0x00); `readAccel(): array{x: float, y: float, z: float}` (в g, ±2g: raw/16384), `readGyro(): array{x: float, y: float, z: float}` (°/с, ±250: raw/131), `readTemperature(): float` (°C: raw/340 + 36.53)
  - `AbstractBoard::mpu6050(int $address = 0x68): Mpu6050`
  - Регистры: ACCEL 0x3B (6 байт), TEMP 0x41 (2 байта), GYRO 0x43 (6 байт); значения int16 big-endian со знаком

- [ ] **Step 1: Написать падающие тесты**

`tests/Device/Mpu6050Test.php`:
```php
use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

it('конструктор будит чип записью в регистр питания', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->mpu6050();
    expect($board->fakeI2c()->writes)->toBe([[0x68, "\x6B\x00"]]);
});

it('readAccel декодирует int16 и масштабирует в g', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $mpu = $board->mpu6050();
    // X=+16384 (1g), Y=-16384 (-1g), Z=0
    $board->fakeI2c()->queueRead("\x40\x00\xC0\x00\x00\x00");
    $accel = $mpu->readAccel();
    expect($accel['x'])->toEqualWithDelta(1.0, 0.001)
        ->and($accel['y'])->toEqualWithDelta(-1.0, 0.001)
        ->and($accel['z'])->toEqualWithDelta(0.0, 0.001);
});

it('readGyro масштабирует в градусы в секунду', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $mpu = $board->mpu6050();
    // X=+131 → 1.0 °/с
    $board->fakeI2c()->queueRead("\x00\x83\x00\x00\x00\x00");
    expect($mpu->readGyro()['x'])->toEqualWithDelta(1.0, 0.01);
});

it('readTemperature переводит в цельсии', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $mpu = $board->mpu6050();
    // raw 0 → 36.53 °C
    $board->fakeI2c()->queueRead("\x00\x00");
    expect($mpu->readTemperature())->toEqualWithDelta(36.53, 0.01);
});
```

- [ ] **Step 2: Убедиться, что тесты падают**

Run: `./vendor/bin/pest tests/Device/Mpu6050Test.php`
Expected: FAIL — `Call to undefined method Femus\Adapter\Fake\FakeBoard::mpu6050()`

- [ ] **Step 3: Реализовать**

`src/Device/Mpu6050.php`:
```php
namespace Femus\Device;

use Femus\Contracts\I2cBus;

/** Гироскоп/акселерометр MPU-6050 (модуль GY-521). */
final class Mpu6050
{
    private const REG_PWR_MGMT_1 = 0x6B;
    private const REG_ACCEL = 0x3B;
    private const REG_TEMP = 0x41;
    private const REG_GYRO = 0x43;

    public function __construct(
        private readonly I2cBus $bus,
        private readonly int $address = 0x68,
    ) {
        $bus->write($address, chr(self::REG_PWR_MGMT_1) . "\x00"); // выход из sleep
    }

    /** @return array{x: float, y: float, z: float} ускорение в g (диапазон ±2g) */
    public function readAccel(): array
    {
        [$x, $y, $z] = $this->readThreeInt16(self::REG_ACCEL);

        return ['x' => $x / 16384.0, 'y' => $y / 16384.0, 'z' => $z / 16384.0];
    }

    /** @return array{x: float, y: float, z: float} угловая скорость °/с (диапазон ±250) */
    public function readGyro(): array
    {
        [$x, $y, $z] = $this->readThreeInt16(self::REG_GYRO);

        return ['x' => $x / 131.0, 'y' => $y / 131.0, 'z' => $z / 131.0];
    }

    public function readTemperature(): float
    {
        $raw = $this->readRegister(self::REG_TEMP, 2);

        return $this->int16($raw, 0) / 340.0 + 36.53;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function readThreeInt16(int $register): array
    {
        $raw = $this->readRegister($register, 6);

        return [$this->int16($raw, 0), $this->int16($raw, 2), $this->int16($raw, 4)];
    }

    private function readRegister(int $register, int $length): string
    {
        return $this->bus->readRegister($this->address, $register, $length);
    }

    private function int16(string $bytes, int $offset): int
    {
        $value = (ord($bytes[$offset]) << 8) | ord($bytes[$offset + 1]);

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }
}
```

В `src/AbstractBoard.php` добавить (импорт `Femus\Device\Mpu6050`):
```php
    public function mpu6050(int $address = 0x68): Mpu6050
    {
        return new Mpu6050($this->i2c(), $address);
    }
```

- [ ] **Step 4: Прогнать все тесты**

Run: `composer test`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Device/Mpu6050.php src/AbstractBoard.php tests/Device/Mpu6050Test.php
git commit -m "feat: драйвер MPU-6050 (GY-521)"
```

---

### Task 9: Примеры, схемы подключения, README и чеклист железа

**Files:**
- Create: `examples/water-level.php`, `examples/lcd-clock.php`, `examples/gyro-dump.php`, `docs/devices/analog-sensor.md`, `docs/devices/lcd1602.md`, `docs/devices/mpu6050.md`
- Modify: `README.md` (секция Статус), `docs/hardware-runs.md` (чеклист аналог/I2C)

**Interfaces:**
- Consumes: всё из Tasks 1–8

- [ ] **Step 1: Написать примеры**

`examples/water-level.php`:
```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// Датчик уровня воды: S → A0, + → 5V, − → GND
$board = Board::firmata($argv[1] ?? '/dev/ttyUSB0');

$sensor = $board->analogSensor(0, threshold: 0.02);

$sensor->onChange(function (float $level) {
    printf("Уровень: %.1f%%\n", $level * 100);
});

echo "Опусти датчик в воду. Ctrl+C для выхода.\n";
$board->run();
```

`examples/lcd-clock.php`:
```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// LCD1602 с I2C-бэкпаком: VCC → 5V, GND → GND, SDA → A4, SCL → A5
$board = Board::firmata($argv[1] ?? '/dev/ttyUSB0');

$lcd = $board->lcd1602();
$lcd->write('femus');

$board->loop()->addPeriodicTimer(1.0, function () use ($lcd) {
    $lcd->setCursor(0, 1);
    $lcd->write(date('H:i:s'));
});

echo "Часы на LCD. Ctrl+C для выхода.\n";
$board->run();
```

`examples/gyro-dump.php`:
```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// GY-521: VCC → 5V, GND → GND, SDA → A4, SCL → A5
$board = Board::firmata($argv[1] ?? '/dev/ttyUSB0');

$mpu = $board->mpu6050();

$board->loop()->addPeriodicTimer(0.5, function () use ($mpu) {
    $a = $mpu->readAccel();
    printf("accel: x=%+.2fg y=%+.2fg z=%+.2fg  t=%.1f°C\n", $a['x'], $a['y'], $a['z'], $mpu->readTemperature());
});

echo "Наклоняй плату. Ctrl+C для выхода.\n";
$board->run();
```

- [ ] **Step 2: Написать схемы подключения**

`docs/devices/analog-sensor.md`:
```markdown
# Аналоговые датчики (уровень воды, фоторезистор, джойстик)

## Подключение к Arduino (Firmata)

| Пин датчика | Arduino |
|-------------|---------|
| S (сигнал)  | A0      |
| + (питание) | 5V      |
| − (земля)   | GND     |

Каналы A0–A5 = analogSensor(0)…analogSensor(5).

## Код

```php
$sensor = $board->analogSensor(0);
echo $sensor->read(); // 0.0–1.0
$sensor->onChange(fn (float $v) => print("{$v}\n"));
```

## Проверка

`php examples/water-level.php <порт>` — опусти датчик в стакан, проценты растут.
```

`docs/devices/lcd1602.md`:
```markdown
# LCD 16x2 (I2C-бэкпак PCF8574)

## Подключение к Arduino (Firmata)

| Пин бэкпака | Arduino |
|-------------|---------|
| VCC         | 5V      |
| GND         | GND     |
| SDA         | A4      |
| SCL         | A5      |

Адрес по умолчанию 0x27 (у части модулей 0x3F: `$board->lcd1602(0x3F)`).
Если экран пуст — подкрути потенциометр контраста на бэкпаке.

## Код

```php
$lcd = $board->lcd1602();
$lcd->write('Privet');
$lcd->setCursor(0, 1);
$lcd->write('femus!');
```

## Проверка

`php examples/lcd-clock.php <порт>` — первая строка «femus», вторая — тикающие часы.
```

`docs/devices/mpu6050.md`:
```markdown
# MPU-6050 гироскоп/акселерометр (модуль GY-521)

## Подключение к Arduino (Firmata)

| Пин GY-521 | Arduino |
|------------|---------|
| VCC        | 5V      |
| GND        | GND     |
| SDA        | A4      |
| SCL        | A5      |

Адрес 0x68 (0x69, если пин AD0 подтянут к питанию: `$board->mpu6050(0x69)`).

## Код

```php
$mpu = $board->mpu6050();
$a = $mpu->readAccel();   // ['x' => g, 'y' => g, 'z' => g]
$g = $mpu->readGyro();    // ['x' => °/с, ...]
$t = $mpu->readTemperature();
```

Плата лежит на столе → z ≈ +1.0g, x и y ≈ 0.

## Проверка

`php examples/gyro-dump.php <порт>` — наклоняй плату, значения осей меняются.
```

- [ ] **Step 3: Обновить README и hardware-runs**

В `README.md` заменить абзац «Статус» на:
```markdown
## Статус

В разработке. Готово: event loop, Firmata-адаптер (цифровой I/O, аналоговые
входы, I2C), устройства Led / Relay / Buzzer / Button / MotionSensor /
AnalogSensor / Lcd1602 / Mpu6050, FakeBoard для тестов без железа.
Дальше по roadmap: Linux/FFI-адаптер (Raspberry Pi), тензодатчик HX711,
GSM/AT-стек, CLI-отладка.
```

В `docs/hardware-runs.md` добавить в конец:
```markdown
## Release 2026-08-03-analog-i2c

### Testing Checklist (Pending Human Execution)

1. `php examples/water-level.php <порт>` — датчик уровня воды на A0, проценты меняются
2. `php examples/lcd-clock.php <порт>` — LCD показывает «femus» и часы
3. `php examples/gyro-dump.php <порт>` — GY-521, z ≈ +1g на столе, реагирует на наклон
4. Записать результаты ниже

### Run 1
- Date: (pending)
- Board: (pending)
- Result: (pending)
```

- [ ] **Step 4: Прогнать все тесты**

Run: `composer test`
Expected: PASS (php -l на примерах: `php -l examples/water-level.php examples/lcd-clock.php examples/gyro-dump.php` — без ошибок; исполнить их без железа нельзя)

- [ ] **Step 5: Commit**

```bash
git add examples docs README.md
git commit -m "docs: примеры и схемы подключения аналоговых и I2C устройств"
```
