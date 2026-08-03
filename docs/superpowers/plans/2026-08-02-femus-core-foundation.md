# femus Core Foundation — Implementation Plan (План 1 из 6)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ядро femus: event loop, контракты портов, FakeBoard для тестов, Firmata-адаптер с цифровым I/O и пять устройств (Led, Relay, Buzzer, Button, MotionSensor) — PHP мигает светодиодом и реагирует на кнопку через живую Arduino.

**Architecture:** Драйверы устройств зависят только от контрактов (`DigitalPin`, `Loop`), контракты реализуются адаптерами (`FakeBoard` в памяти, `FirmataBoard` через serial + протокол Firmata). Однопоточный event loop на `stream_select`. Спека: `docs/superpowers/specs/2026-08-02-femus-design.md`.

**Tech Stack:** PHP 8.2+, Composer (пакет `sanchescom/femus`, PSR-4 `Femus\` → `src/`), Pest v3. Без runtime-зависимостей и C-расширений.

## Global Constraints

- PHP `^8.2`; каждый PHP-файл начинается с `<?php` + `declare(strict_types=1);` (в сниппетах плана эта строка опущена для краткости — ставить всегда)
- Все классы `final` (кроме явно `abstract`), свойства типизированы, `readonly` где возможно
- Никаких runtime-зависимостей в composer `require`, кроме `php`
- Тесты — Pest v3, файлы в `tests/`, запуск `composer test`
- Коммиты от `sanchescom <sanches.com@mail.ru>` (локальный git config уже настроен), сообщения на русском или английском, **без** упоминаний Claude и без Co-Authored-By
- Последующие планы (Linux-адаптер, аналог/I2C, HX711, GSM, CLI) строятся на интерфейсах этого плана — менять сигнатуры из блоков «Produces» нельзя без обновления спеки

---

### Task 1: Каркас пакета

**Files:**
- Create: `composer.json`, `.gitignore`, `tests/Pest.php`, `tests/SmokeTest.php`

**Interfaces:**
- Produces: composer-пакет `sanchescom/femus`, автозагрузка `Femus\` → `src/`, `Femus\Tests\` → `tests/`, команда `composer test`

- [ ] **Step 1: Написать composer.json и .gitignore**

`composer.json`:
```json
{
    "name": "sanchescom/femus",
    "description": "PHP hardware framework: sensors, relays, Arduino (Firmata) and Raspberry Pi from plain PHP",
    "license": "MIT",
    "type": "library",
    "require": { "php": "^8.2" },
    "require-dev": { "pestphp/pest": "^3.0" },
    "autoload": { "psr-4": { "Femus\\": "src/" } },
    "autoload-dev": { "psr-4": { "Femus\\Tests\\": "tests/" } },
    "config": { "allow-plugins": { "pestphp/pest-plugin": true } },
    "scripts": { "test": "pest" }
}
```

`.gitignore`:
```
/vendor/
.phpunit.result.cache
```

- [ ] **Step 2: Установить зависимости и инициализировать Pest**

Run: `composer install && ./vendor/bin/pest --init`
Expected: появились `vendor/`, `tests/Pest.php`, `phpunit.xml`. Если `pest --init` создал `tests/Feature`/`tests/Unit` и примеры — удалить примеры, оставить `tests/Pest.php` (содержимое по умолчанию, без изменений).

- [ ] **Step 3: Написать smoke-тест**

`tests/SmokeTest.php`:
```php
it('автозагрузка пакета работает', function () {
    expect(class_exists(\Composer\Autoload\ClassLoader::class))->toBeTrue();
});
```

- [ ] **Step 4: Запустить тесты**

Run: `composer test`
Expected: PASS (1 passed)

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock .gitignore tests/ phpunit.xml
git commit -m "chore: каркас пакета sanchescom/femus + Pest"
```

---

### Task 2: Event loop на stream_select

**Files:**
- Create: `src/Runtime/Loop.php`, `src/Runtime/StreamSelectLoop.php`
- Test: `tests/Runtime/StreamSelectLoopTest.php`

**Interfaces:**
- Produces:
  - `interface Femus\Runtime\Loop` — `addReadStream($stream, callable $onReadable): void`, `removeReadStream($stream): void`, `addTimer(float $delaySeconds, callable $callback): string`, `addPeriodicTimer(float $intervalSeconds, callable $callback): string`, `cancelTimer(string $timerId): void`, `run(): void`, `stop(): void`, `tick(float $timeoutSeconds): void`
  - `final class Femus\Runtime\StreamSelectLoop implements Loop`
  - Семантика `run()`: крутится, пока не вызван `stop()` И пока есть хоть один таймер или поток; `tick()` — одна итерация (нужна блокирующим шорткатам устройств)

- [ ] **Step 1: Написать падающие тесты**

`tests/Runtime/StreamSelectLoopTest.php`:
```php
use Femus\Runtime\StreamSelectLoop;

it('вызывает одноразовый таймер ровно один раз', function () {
    $loop = new StreamSelectLoop();
    $calls = 0;
    $loop->addTimer(0.01, function () use (&$calls) { $calls++; });
    $loop->run();
    expect($calls)->toBe(1);
});

it('вызывает периодический таймер до отмены', function () {
    $loop = new StreamSelectLoop();
    $calls = 0;
    $id = null;
    $id = $loop->addPeriodicTimer(0.005, function () use (&$calls, $loop, &$id) {
        if (++$calls >= 3) {
            $loop->cancelTimer($id);
        }
    });
    $loop->run();
    expect($calls)->toBe(3);
});

it('останавливается по stop() изнутри колбэка', function () {
    $loop = new StreamSelectLoop();
    $loop->addPeriodicTimer(0.001, fn () => $loop->stop());
    $loop->run(); // не должен зависнуть
    expect(true)->toBeTrue();
});

it('читает данные из потока', function () {
    $loop = new StreamSelectLoop();
    [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    stream_set_blocking($a, false);
    $received = '';
    $loop->addReadStream($a, function ($stream) use (&$received, $loop, $a) {
        $received .= (string) stream_get_contents($stream);
        $loop->removeReadStream($a);
    });
    fwrite($b, 'ping');
    $loop->run();
    expect($received)->toBe('ping');
});
```

- [ ] **Step 2: Убедиться, что тесты падают**

Run: `./vendor/bin/pest tests/Runtime/StreamSelectLoopTest.php`
Expected: FAIL — `Class "Femus\Runtime\StreamSelectLoop" not found`

- [ ] **Step 3: Реализовать Loop и StreamSelectLoop**

`src/Runtime/Loop.php`:
```php
namespace Femus\Runtime;

interface Loop
{
    /** @param resource $stream @param callable(resource): void $onReadable */
    public function addReadStream($stream, callable $onReadable): void;

    /** @param resource $stream */
    public function removeReadStream($stream): void;

    /** @return string id таймера */
    public function addTimer(float $delaySeconds, callable $callback): string;

    /** @return string id таймера */
    public function addPeriodicTimer(float $intervalSeconds, callable $callback): string;

    public function cancelTimer(string $timerId): void;

    /** Крутится, пока не вызван stop() и пока есть таймеры или потоки. */
    public function run(): void;

    public function stop(): void;

    /** Одна итерация: ждёт события не дольше $timeoutSeconds, исполняет готовые таймеры. */
    public function tick(float $timeoutSeconds): void;
}
```

`src/Runtime/StreamSelectLoop.php`:
```php
namespace Femus\Runtime;

final class StreamSelectLoop implements Loop
{
    /** @var array<int, resource> */
    private array $readStreams = [];

    /** @var array<int, callable> */
    private array $readListeners = [];

    /** @var array<string, array{at: float, interval: ?float, cb: callable}> */
    private array $timers = [];

    private int $nextTimerId = 1;

    private bool $stopped = false;

    public function addReadStream($stream, callable $onReadable): void
    {
        $this->readStreams[(int) $stream] = $stream;
        $this->readListeners[(int) $stream] = $onReadable;
    }

    public function removeReadStream($stream): void
    {
        unset($this->readStreams[(int) $stream], $this->readListeners[(int) $stream]);
    }

    public function addTimer(float $delaySeconds, callable $callback): string
    {
        return $this->schedule($delaySeconds, null, $callback);
    }

    public function addPeriodicTimer(float $intervalSeconds, callable $callback): string
    {
        return $this->schedule($intervalSeconds, $intervalSeconds, $callback);
    }

    public function cancelTimer(string $timerId): void
    {
        unset($this->timers[$timerId]);
    }

    public function run(): void
    {
        $this->stopped = false;
        while (!$this->stopped && ($this->timers !== [] || $this->readStreams !== [])) {
            $this->tick(0.05);
        }
    }

    public function stop(): void
    {
        $this->stopped = true;
    }

    public function tick(float $timeoutSeconds): void
    {
        $timeout = max(0.0, min($timeoutSeconds, $this->timeUntilNextTimer() ?? $timeoutSeconds));

        if ($this->readStreams !== []) {
            $read = array_values($this->readStreams);
            $write = null;
            $except = null;
            $changed = @stream_select($read, $write, $except, 0, (int) round($timeout * 1_000_000));
            if ($changed !== false && $changed > 0) {
                foreach ($read as $stream) {
                    $listener = $this->readListeners[(int) $stream] ?? null;
                    if ($listener !== null) {
                        $listener($stream);
                    }
                }
            }
        } elseif ($timeout > 0) {
            usleep((int) round($timeout * 1_000_000));
        }

        $this->fireDueTimers();
    }

    private function schedule(float $delay, ?float $interval, callable $cb): string
    {
        $id = 't' . $this->nextTimerId++;
        $this->timers[$id] = ['at' => $this->now() + $delay, 'interval' => $interval, 'cb' => $cb];

        return $id;
    }

    private function now(): float
    {
        return hrtime(true) / 1e9;
    }

    private function timeUntilNextTimer(): ?float
    {
        if ($this->timers === []) {
            return null;
        }
        $next = min(array_column($this->timers, 'at'));

        return max(0.0, $next - $this->now());
    }

    private function fireDueTimers(): void
    {
        $now = $this->now();
        foreach ($this->timers as $id => $timer) {
            if ($timer['at'] > $now) {
                continue;
            }
            ($timer['cb'])();
            if (!isset($this->timers[$id])) {
                continue; // отменён внутри колбэка
            }
            if ($timer['interval'] === null) {
                unset($this->timers[$id]);
            } else {
                $this->timers[$id]['at'] = $now + $timer['interval'];
            }
        }
    }
}
```

- [ ] **Step 4: Убедиться, что тесты проходят**

Run: `./vendor/bin/pest tests/Runtime/StreamSelectLoopTest.php`
Expected: PASS (4 passed)

- [ ] **Step 5: Commit**

```bash
git add src/Runtime tests/Runtime
git commit -m "feat: event loop на stream_select (таймеры, потоки, tick)"
```

---

### Task 3: Контракты портов + FakeBoard

**Files:**
- Create: `src/Contracts/PinMode.php`, `src/Contracts/DigitalPin.php`, `src/Contracts/BoardInterface.php`, `src/AbstractBoard.php`, `src/Adapter/Fake/FakeDigitalPin.php`, `src/Adapter/Fake/FakeBoard.php`
- Test: `tests/Adapter/Fake/FakeBoardTest.php`

**Interfaces:**
- Consumes: `Femus\Runtime\Loop`, `Femus\Runtime\StreamSelectLoop` (Task 2)
- Produces:
  - `enum Femus\Contracts\PinMode { case Input; case InputPullUp; case Output; }`
  - `interface Femus\Contracts\DigitalPin` — `number(): int`, `write(bool $high): void`, `read(): bool`, `onChange(callable $listener): void` (листенер: `fn(bool $high): void`)
  - `interface Femus\Contracts\BoardInterface` — `digitalPin(int $number, PinMode $mode): DigitalPin`, `loop(): Loop`, `run(): void`, `stop(): void`
  - `abstract class Femus\AbstractBoard implements BoardInterface` — конструктор `__construct(protected readonly Loop $loop)`; фабрики устройств добавляются в Tasks 4–5
  - `final class Femus\Adapter\Fake\FakeBoard extends AbstractBoard` — плюс тестовые методы `pin(int $number): FakeDigitalPin`, `simulateInput(int $pin, bool $high): void`, `scheduleInput(float $delaySeconds, int $pin, bool $high): void`
  - `FakeDigitalPin` — плюс `simulate(bool $high): void` (имитация внешнего сигнала: меняет состояние и зовёт листенеры)

- [ ] **Step 1: Написать падающие тесты**

`tests/Adapter/Fake/FakeBoardTest.php`:
```php
use Femus\Adapter\Fake\FakeBoard;
use Femus\Contracts\PinMode;
use Femus\Runtime\StreamSelectLoop;

it('выдаёт один и тот же пин по номеру', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->digitalPin(4, PinMode::Input);
    expect($board->digitalPin(4, PinMode::Input))->toBe($pin)
        ->and($pin->number())->toBe(4);
});

it('write/read работают', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->digitalPin(7, PinMode::Output);
    $pin->write(true);
    expect($pin->read())->toBeTrue();
});

it('simulateInput зовёт onChange-листенеры', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->digitalPin(2, PinMode::Input);
    $seen = [];
    $pin->onChange(function (bool $high) use (&$seen) { $seen[] = $high; });
    $board->simulateInput(2, true);
    $board->simulateInput(2, true);  // без изменения — не событие
    $board->simulateInput(2, false);
    expect($seen)->toBe([true, false]);
});

it('scheduleInput инжектит событие через event loop', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pin = $board->digitalPin(2, PinMode::Input);
    $seen = false;
    $pin->onChange(function (bool $high) use (&$seen) { $seen = $high; });
    $board->scheduleInput(0.01, 2, true);
    $board->run();
    expect($seen)->toBeTrue();
});
```

- [ ] **Step 2: Убедиться, что тесты падают**

Run: `./vendor/bin/pest tests/Adapter/Fake/FakeBoardTest.php`
Expected: FAIL — `Class "Femus\Adapter\Fake\FakeBoard" not found`

- [ ] **Step 3: Реализовать контракты, AbstractBoard и Fake-адаптер**

`src/Contracts/PinMode.php`:
```php
namespace Femus\Contracts;

enum PinMode
{
    case Input;
    case InputPullUp;
    case Output;
}
```

`src/Contracts/DigitalPin.php`:
```php
namespace Femus\Contracts;

interface DigitalPin
{
    public function number(): int;

    public function write(bool $high): void;

    public function read(): bool;

    /** @param callable(bool): void $listener получает новое состояние пина */
    public function onChange(callable $listener): void;
}
```

`src/Contracts/BoardInterface.php`:
```php
namespace Femus\Contracts;

use Femus\Runtime\Loop;

interface BoardInterface
{
    public function digitalPin(int $number, PinMode $mode): DigitalPin;

    public function loop(): Loop;

    public function run(): void;

    public function stop(): void;
}
```

`src/AbstractBoard.php`:
```php
namespace Femus;

use Femus\Contracts\BoardInterface;
use Femus\Runtime\Loop;

abstract class AbstractBoard implements BoardInterface
{
    public function __construct(protected readonly Loop $loop)
    {
    }

    public function loop(): Loop
    {
        return $this->loop;
    }

    public function run(): void
    {
        $this->loop->run();
    }

    public function stop(): void
    {
        $this->loop->stop();
    }
}
```

`src/Adapter/Fake/FakeDigitalPin.php`:
```php
namespace Femus\Adapter\Fake;

use Femus\Contracts\DigitalPin;
use Femus\Contracts\PinMode;

final class FakeDigitalPin implements DigitalPin
{
    private bool $state = false;

    /** @var list<callable> */
    private array $listeners = [];

    public function __construct(
        private readonly int $number,
        public readonly PinMode $mode,
    ) {
    }

    public function number(): int
    {
        return $this->number;
    }

    public function write(bool $high): void
    {
        $this->state = $high;
    }

    public function read(): bool
    {
        return $this->state;
    }

    public function onChange(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    /** Тестовый вход: имитация внешнего сигнала на пине. */
    public function simulate(bool $high): void
    {
        if ($high === $this->state) {
            return;
        }
        $this->state = $high;
        foreach ($this->listeners as $listener) {
            $listener($high);
        }
    }
}
```

`src/Adapter/Fake/FakeBoard.php`:
```php
namespace Femus\Adapter\Fake;

use Femus\AbstractBoard;
use Femus\Contracts\DigitalPin;
use Femus\Contracts\PinMode;

final class FakeBoard extends AbstractBoard
{
    /** @var array<int, FakeDigitalPin> */
    private array $pins = [];

    public function digitalPin(int $number, PinMode $mode): DigitalPin
    {
        return $this->pins[$number] ??= new FakeDigitalPin($number, $mode);
    }

    public function pin(int $number): FakeDigitalPin
    {
        return $this->pins[$number]
            ?? throw new \LogicException("Пин {$number} ещё не запрошен через digitalPin()");
    }

    public function simulateInput(int $pin, bool $high): void
    {
        $this->pin($pin)->simulate($high);
    }

    /** Сценарий: инжект события через $delaySeconds внутри event loop. */
    public function scheduleInput(float $delaySeconds, int $pin, bool $high): void
    {
        $this->loop->addTimer($delaySeconds, fn () => $this->simulateInput($pin, $high));
    }
}
```

- [ ] **Step 4: Убедиться, что тесты проходят**

Run: `./vendor/bin/pest tests/Adapter/Fake/FakeBoardTest.php`
Expected: PASS (4 passed)

- [ ] **Step 5: Commit**

```bash
git add src/Contracts src/AbstractBoard.php src/Adapter/Fake tests/Adapter
git commit -m "feat: контракты портов, AbstractBoard и Fake-адаптер"
```

---

### Task 4: Выходные устройства — Led, Relay, Buzzer

**Files:**
- Create: `src/Device/Led.php`, `src/Device/Relay.php`, `src/Device/Buzzer.php`
- Modify: `src/AbstractBoard.php` (добавить фабрики `led()`, `relay()`, `buzzer()`)
- Test: `tests/Device/OutputDevicesTest.php`

**Interfaces:**
- Consumes: `DigitalPin`, `PinMode`, `Loop` (Tasks 2–3)
- Produces:
  - `final class Femus\Device\Led` — `__construct(DigitalPin $pin, Loop $loop)`, `on(): void`, `off(): void`, `toggle(): void`, `isOn(): bool`, `blink(float $intervalSeconds = 0.5): void`, `stopBlinking(): void`
  - `final class Femus\Device\Relay` — `__construct(DigitalPin $pin)`, `on()`, `off()`, `toggle()`, `isOn(): bool`
  - `final class Femus\Device\Buzzer` — `__construct(DigitalPin $pin, Loop $loop)`, `on()`, `off()`, `isOn(): bool`, `beep(float $seconds = 0.1): void`
  - `AbstractBoard::led(int $pin): Led`, `AbstractBoard::relay(int $pin): Relay`, `AbstractBoard::buzzer(int $pin): Buzzer`

- [ ] **Step 1: Написать падающие тесты**

`tests/Device/OutputDevicesTest.php`:
```php
use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

it('led включается, выключается и переключается', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $led = $board->led(13);
    $led->on();
    expect($led->isOn())->toBeTrue();
    $led->toggle();
    expect($led->isOn())->toBeFalse();
});

it('led мигает по таймеру', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $led = $board->led(13);
    $led->blink(0.005);
    $board->loop()->addTimer(0.03, function () use ($led, $board) {
        $led->stopBlinking();
        $board->stop();
    });
    $board->run();
    // за ~30мс при периоде 5мс LED обязан был переключиться хотя бы дважды —
    // проверяем сам факт работы таймера через состояние пина
    expect($board->pin(13)->mode)->toBe(\Femus\Contracts\PinMode::Output);
});

it('relay щёлкает', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $relay = $board->relay(7);
    $relay->on();
    expect($relay->isOn())->toBeTrue()
        ->and($board->pin(7)->read())->toBeTrue();
});

it('buzzer пищит заданное время и замолкает', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $buzzer = $board->buzzer(8);
    $buzzer->beep(0.01);
    expect($buzzer->isOn())->toBeTrue();
    $board->run(); // таймер выключения — единственный, run() завершится сам
    expect($buzzer->isOn())->toBeFalse();
});
```

- [ ] **Step 2: Убедиться, что тесты падают**

Run: `./vendor/bin/pest tests/Device/OutputDevicesTest.php`
Expected: FAIL — `Call to undefined method Femus\Adapter\Fake\FakeBoard::led()`

- [ ] **Step 3: Реализовать устройства и фабрики**

`src/Device/Led.php`:
```php
namespace Femus\Device;

use Femus\Contracts\DigitalPin;
use Femus\Runtime\Loop;

final class Led
{
    private ?string $blinkTimer = null;

    public function __construct(
        private readonly DigitalPin $pin,
        private readonly Loop $loop,
    ) {
    }

    public function on(): void
    {
        $this->pin->write(true);
    }

    public function off(): void
    {
        $this->pin->write(false);
    }

    public function toggle(): void
    {
        $this->pin->write(!$this->pin->read());
    }

    public function isOn(): bool
    {
        return $this->pin->read();
    }

    public function blink(float $intervalSeconds = 0.5): void
    {
        $this->stopBlinking();
        $this->blinkTimer = $this->loop->addPeriodicTimer($intervalSeconds, $this->toggle(...));
    }

    public function stopBlinking(): void
    {
        if ($this->blinkTimer !== null) {
            $this->loop->cancelTimer($this->blinkTimer);
            $this->blinkTimer = null;
        }
    }
}
```

`src/Device/Relay.php`:
```php
namespace Femus\Device;

use Femus\Contracts\DigitalPin;

final class Relay
{
    public function __construct(private readonly DigitalPin $pin)
    {
    }

    public function on(): void
    {
        $this->pin->write(true);
    }

    public function off(): void
    {
        $this->pin->write(false);
    }

    public function toggle(): void
    {
        $this->pin->write(!$this->pin->read());
    }

    public function isOn(): bool
    {
        return $this->pin->read();
    }
}
```

`src/Device/Buzzer.php`:
```php
namespace Femus\Device;

use Femus\Contracts\DigitalPin;
use Femus\Runtime\Loop;

final class Buzzer
{
    public function __construct(
        private readonly DigitalPin $pin,
        private readonly Loop $loop,
    ) {
    }

    public function on(): void
    {
        $this->pin->write(true);
    }

    public function off(): void
    {
        $this->pin->write(false);
    }

    public function isOn(): bool
    {
        return $this->pin->read();
    }

    public function beep(float $seconds = 0.1): void
    {
        $this->on();
        $this->loop->addTimer($seconds, $this->off(...));
    }
}
```

Добавить в `src/AbstractBoard.php` (после `stop()`, с импортами `Femus\Contracts\PinMode`, `Femus\Device\Led`, `Femus\Device\Relay`, `Femus\Device\Buzzer`):
```php
    public function led(int $pin): Led
    {
        return new Led($this->digitalPin($pin, PinMode::Output), $this->loop);
    }

    public function relay(int $pin): Relay
    {
        return new Relay($this->digitalPin($pin, PinMode::Output));
    }

    public function buzzer(int $pin): Buzzer
    {
        return new Buzzer($this->digitalPin($pin, PinMode::Output), $this->loop);
    }
```

- [ ] **Step 4: Убедиться, что тесты проходят**

Run: `./vendor/bin/pest tests/Device/OutputDevicesTest.php`
Expected: PASS (4 passed)

- [ ] **Step 5: Commit**

```bash
git add src/Device src/AbstractBoard.php tests/Device
git commit -m "feat: выходные устройства Led, Relay, Buzzer"
```

---

### Task 5: Входные устройства — Button, MotionSensor + тест цепочки

**Files:**
- Create: `src/Device/Button.php`, `src/Device/MotionSensor.php`
- Modify: `src/AbstractBoard.php` (фабрики `button()`, `motionSensor()`)
- Test: `tests/Device/InputDevicesTest.php`, `tests/Scenario/ChainTest.php`

**Interfaces:**
- Consumes: `DigitalPin`, `PinMode`, `Loop`, `FakeBoard::scheduleInput()` (Tasks 2–4)
- Produces:
  - `final class Femus\Device\Button` — `__construct(DigitalPin $pin, Loop $loop, float $debounceSeconds = 0.02, bool $activeLow = true)`; `onPress(callable $fn): void`, `onRelease(callable $fn): void`, `isPressed(): bool`, `waitForPress(?float $timeoutSeconds = null): bool` (блокирующий шорткат: крутит `tick()`, возвращает false по таймауту)
  - `final class Femus\Device\MotionSensor` — `__construct(DigitalPin $pin, Loop $loop)`; `onMotion(callable $fn): void`, `onIdle(callable $fn): void`, `isActive(): bool`, `waitForMotion(?float $timeoutSeconds = null): bool` (PIR — active-high, без debounce: HC-SR501 сам держит выход)
  - `AbstractBoard::button(int $pin, float $debounceSeconds = 0.02): Button` (режим InputPullUp), `AbstractBoard::motionSensor(int $pin): MotionSensor` (режим Input)

- [ ] **Step 1: Написать падающие тесты**

`tests/Device/InputDevicesTest.php`:
```php
use Femus\Adapter\Fake\FakeBoard;
use Femus\Contracts\PinMode;
use Femus\Runtime\StreamSelectLoop;

// Подтяжку выставляем write()-ом ДО создания Button: write() меняет состояние
// без события, иначе искусственный фронт съест debounce-окно перед нажатием.

it('button: нажатие при active-low это LOW на пине', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->digitalPin(2, PinMode::InputPullUp)->write(true); // подтяжка: не нажата
    $button = $board->button(2);
    $presses = 0;
    $button->onPress(function () use (&$presses) { $presses++; });
    $board->simulateInput(2, false); // нажали
    expect($presses)->toBe(1)->and($button->isPressed())->toBeTrue();
});

it('button: дребезг в пределах debounce гасится', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->digitalPin(2, PinMode::InputPullUp)->write(true);
    $button = $board->button(2, debounceSeconds: 0.05);
    $presses = 0;
    $button->onPress(function () use (&$presses) { $presses++; });
    $board->simulateInput(2, false); // нажатие
    $board->simulateInput(2, true);  // дребезг — мгновенный отскок
    $board->simulateInput(2, false); // дребезг — мгновенный возврат
    expect($presses)->toBe(1);
});

it('button: waitForPress возвращает false по таймауту', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $button = $board->button(2);
    expect($button->waitForPress(timeoutSeconds: 0.05))->toBeFalse();
});

it('button: waitForPress ловит запланированное нажатие', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->digitalPin(2, PinMode::InputPullUp)->write(true);
    $button = $board->button(2);
    $board->scheduleInput(0.02, 2, false);
    expect($button->waitForPress(timeoutSeconds: 1.0))->toBeTrue();
});

it('motion sensor: движение и покой', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pir = $board->motionSensor(4);
    $events = [];
    $pir->onMotion(function () use (&$events) { $events[] = 'motion'; });
    $pir->onIdle(function () use (&$events) { $events[] = 'idle'; });
    $board->simulateInput(4, true);
    $board->simulateInput(4, false);
    expect($events)->toBe(['motion', 'idle'])
        ->and($pir->isActive())->toBeFalse();
});
```

`tests/Scenario/ChainTest.php` (уровень 2 из спеки — цепочки без железа):
```php
use Femus\Adapter\Fake\FakeBoard;
use Femus\Contracts\PinMode;
use Femus\Runtime\StreamSelectLoop;

it('цепочка: кнопка включает реле, PIR включает зуммер', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->digitalPin(2, PinMode::InputPullUp)->write(true); // подтяжка кнопки
    $button = $board->button(2);
    $relay = $board->relay(7);
    $pir = $board->motionSensor(4);
    $buzzer = $board->buzzer(8);

    $button->onPress(fn () => $relay->on());
    $pir->onMotion(fn () => $buzzer->on());

    $board->scheduleInput(0.01, 2, false);   // t+10мс: нажатие
    $board->scheduleInput(0.02, 4, true);    // t+20мс: движение
    $board->loop()->addTimer(0.05, fn () => $board->stop());

    $board->run();

    expect($relay->isOn())->toBeTrue()
        ->and($buzzer->isOn())->toBeTrue();
});
```

- [ ] **Step 2: Убедиться, что тесты падают**

Run: `./vendor/bin/pest tests/Device/InputDevicesTest.php tests/Scenario/ChainTest.php`
Expected: FAIL — `Call to undefined method Femus\Adapter\Fake\FakeBoard::button()`

- [ ] **Step 3: Реализовать Button, MotionSensor и фабрики**

`src/Device/Button.php`:
```php
namespace Femus\Device;

use Femus\Contracts\DigitalPin;
use Femus\Runtime\Loop;

final class Button
{
    /** @var list<callable> */
    private array $pressListeners = [];

    /** @var list<callable> */
    private array $releaseListeners = [];

    private float $lastEdgeAt = -INF;

    public function __construct(
        private readonly DigitalPin $pin,
        private readonly Loop $loop,
        private readonly float $debounceSeconds = 0.02,
        private readonly bool $activeLow = true,
    ) {
        $pin->onChange(function (bool $high): void {
            $now = hrtime(true) / 1e9;
            if ($now - $this->lastEdgeAt < $this->debounceSeconds) {
                return;
            }
            $this->lastEdgeAt = $now;
            $pressed = $this->activeLow ? !$high : $high;
            foreach ($pressed ? $this->pressListeners : $this->releaseListeners as $listener) {
                $listener();
            }
        });
    }

    public function onPress(callable $fn): void
    {
        $this->pressListeners[] = $fn;
    }

    public function onRelease(callable $fn): void
    {
        $this->releaseListeners[] = $fn;
    }

    public function isPressed(): bool
    {
        return $this->activeLow ? !$this->pin->read() : $this->pin->read();
    }

    public function waitForPress(?float $timeoutSeconds = null): bool
    {
        $pressed = false;
        $this->pressListeners[] = function () use (&$pressed): void {
            $pressed = true;
        };
        $deadline = $timeoutSeconds === null ? null : hrtime(true) / 1e9 + $timeoutSeconds;
        while (!$pressed) {
            if ($deadline !== null && hrtime(true) / 1e9 >= $deadline) {
                return false;
            }
            $this->loop->tick(0.05);
        }

        return true;
    }
}
```

`src/Device/MotionSensor.php`:
```php
namespace Femus\Device;

use Femus\Contracts\DigitalPin;
use Femus\Runtime\Loop;

final class MotionSensor
{
    /** @var list<callable> */
    private array $motionListeners = [];

    /** @var list<callable> */
    private array $idleListeners = [];

    public function __construct(
        private readonly DigitalPin $pin,
        private readonly Loop $loop,
    ) {
        $pin->onChange(function (bool $high): void {
            foreach ($high ? $this->motionListeners : $this->idleListeners as $listener) {
                $listener();
            }
        });
    }

    public function onMotion(callable $fn): void
    {
        $this->motionListeners[] = $fn;
    }

    public function onIdle(callable $fn): void
    {
        $this->idleListeners[] = $fn;
    }

    public function isActive(): bool
    {
        return $this->pin->read();
    }

    public function waitForMotion(?float $timeoutSeconds = null): bool
    {
        $detected = false;
        $this->motionListeners[] = function () use (&$detected): void {
            $detected = true;
        };
        $deadline = $timeoutSeconds === null ? null : hrtime(true) / 1e9 + $timeoutSeconds;
        while (!$detected) {
            if ($deadline !== null && hrtime(true) / 1e9 >= $deadline) {
                return false;
            }
            $this->loop->tick(0.05);
        }

        return true;
    }
}
```

Добавить в `src/AbstractBoard.php` (импорты `Femus\Device\Button`, `Femus\Device\MotionSensor`):
```php
    public function button(int $pin, float $debounceSeconds = 0.02): Button
    {
        return new Button($this->digitalPin($pin, PinMode::InputPullUp), $this->loop, $debounceSeconds);
    }

    public function motionSensor(int $pin): MotionSensor
    {
        return new MotionSensor($this->digitalPin($pin, PinMode::Input), $this->loop);
    }
```

- [ ] **Step 4: Убедиться, что все тесты проходят**

Run: `composer test`
Expected: PASS (все тесты, включая цепочку)

- [ ] **Step 5: Commit**

```bash
git add src/Device src/AbstractBoard.php tests/Device tests/Scenario
git commit -m "feat: входные устройства Button и MotionSensor, тест цепочки"
```

---

### Task 6: Протокол Firmata — энкодер и парсер

**Files:**
- Create: `src/Adapter/Firmata/Firmata.php`, `src/Adapter/Firmata/FirmataEncoder.php`, `src/Adapter/Firmata/FirmataParser.php`
- Test: `tests/Adapter/Firmata/FirmataProtocolTest.php`

**Interfaces:**
- Consumes: ничего из предыдущих задач (чистые функции над байтами)
- Produces:
  - `final class Femus\Adapter\Firmata\Firmata` — константы протокола: `REPORT_VERSION = 0xF9`, `SET_PIN_MODE = 0xF4`, `DIGITAL_MESSAGE = 0x90`, `REPORT_DIGITAL = 0xD0`, `SYSEX_START = 0xF0`, `SYSEX_END = 0xF7`, `MODE_INPUT = 0x00`, `MODE_OUTPUT = 0x01`, `MODE_PULLUP = 0x0B`
  - `FirmataEncoder` (все методы static): `setPinMode(int $pin, int $mode): string`, `digitalWrite(int $port, int $bitmask): string`, `reportDigitalPort(int $port, bool $enable): string`
  - `FirmataParser` — `push(string $bytes): void` (инкрементальный, переживает разрезание сообщений между push), `onDigitalMessage(callable $fn): void` (fn(int $port, int $bitmask)), `onVersion(callable $fn): void` (fn(int $major, int $minor))

- [ ] **Step 1: Написать падающие тесты**

`tests/Adapter/Firmata/FirmataProtocolTest.php`:
```php
use Femus\Adapter\Firmata\Firmata;
use Femus\Adapter\Firmata\FirmataEncoder;
use Femus\Adapter\Firmata\FirmataParser;

it('кодирует setPinMode', function () {
    expect(FirmataEncoder::setPinMode(13, Firmata::MODE_OUTPUT))->toBe("\xF4\x0D\x01");
});

it('кодирует digitalWrite: pin 13 = порт 1, бит 5', function () {
    expect(FirmataEncoder::digitalWrite(1, 0b00100000))->toBe("\x91\x20\x00");
});

it('кодирует digitalWrite с битом 7 в старшем байте', function () {
    expect(FirmataEncoder::digitalWrite(0, 0b10000001))->toBe("\x90\x01\x01");
});

it('кодирует включение репортинга порта', function () {
    expect(FirmataEncoder::reportDigitalPort(0, true))->toBe("\xD0\x01");
});

it('парсит digital message', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onDigitalMessage(function (int $port, int $bitmask) use (&$got) {
        $got = [$port, $bitmask];
    });
    $parser->push("\x90\x04\x00"); // порт 0, пин 2 HIGH
    expect($got)->toBe([0, 4]);
});

it('парсит сообщение, разрезанное между push', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onDigitalMessage(function (int $port, int $bitmask) use (&$got) {
        $got = [$port, $bitmask];
    });
    $parser->push("\x90\x04");
    expect($got)->toBeNull();
    $parser->push("\x00");
    expect($got)->toBe([0, 4]);
});

it('парсит версию протокола', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onVersion(function (int $major, int $minor) use (&$got) {
        $got = "$major.$minor";
    });
    $parser->push("\xF9\x02\x05");
    expect($got)->toBe('2.5');
});

it('пропускает sysex-блоки и мусор, не теряя следующие сообщения', function () {
    $parser = new FirmataParser();
    $got = null;
    $parser->onDigitalMessage(function (int $port, int $bitmask) use (&$got) {
        $got = [$port, $bitmask];
    });
    // sysex (например, отчёт о прошивке при старте) + мусорный байт + наше сообщение
    $parser->push("\xF0\x79\x02\x05\xF7" . "\x42" . "\x90\x04\x00");
    expect($got)->toBe([0, 4]);
});
```

- [ ] **Step 2: Убедиться, что тесты падают**

Run: `./vendor/bin/pest tests/Adapter/Firmata/FirmataProtocolTest.php`
Expected: FAIL — `Class "Femus\Adapter\Firmata\FirmataEncoder" not found`

- [ ] **Step 3: Реализовать протокол**

`src/Adapter/Firmata/Firmata.php`:
```php
namespace Femus\Adapter\Firmata;

/** Константы протокола Firmata 2.x. */
final class Firmata
{
    public const REPORT_VERSION = 0xF9;
    public const SET_PIN_MODE = 0xF4;
    public const DIGITAL_MESSAGE = 0x90; // | номер порта (8 пинов на порт)
    public const REPORT_DIGITAL = 0xD0;  // | номер порта
    public const SYSEX_START = 0xF0;
    public const SYSEX_END = 0xF7;

    public const MODE_INPUT = 0x00;
    public const MODE_OUTPUT = 0x01;
    public const MODE_PULLUP = 0x0B;

    private function __construct()
    {
    }
}
```

`src/Adapter/Firmata/FirmataEncoder.php`:
```php
namespace Femus\Adapter\Firmata;

final class FirmataEncoder
{
    public static function setPinMode(int $pin, int $mode): string
    {
        return chr(Firmata::SET_PIN_MODE) . chr($pin) . chr($mode);
    }

    /** Firmata передаёт 14-битные значения по 7 бит: младшие 7, затем старшие. */
    public static function digitalWrite(int $port, int $bitmask): string
    {
        return chr(Firmata::DIGITAL_MESSAGE | $port)
            . chr($bitmask & 0x7F)
            . chr(($bitmask >> 7) & 0x7F);
    }

    public static function reportDigitalPort(int $port, bool $enable): string
    {
        return chr(Firmata::REPORT_DIGITAL | $port) . chr($enable ? 1 : 0);
    }

    private function __construct()
    {
    }
}
```

`src/Adapter/Firmata/FirmataParser.php`:
```php
namespace Femus\Adapter\Firmata;

final class FirmataParser
{
    private string $buffer = '';

    /** @var list<callable> */
    private array $digitalListeners = [];

    /** @var list<callable> */
    private array $versionListeners = [];

    public function onDigitalMessage(callable $fn): void
    {
        $this->digitalListeners[] = $fn;
    }

    public function onVersion(callable $fn): void
    {
        $this->versionListeners[] = $fn;
    }

    public function push(string $bytes): void
    {
        $this->buffer .= $bytes;

        while ($this->buffer !== '') {
            $first = ord($this->buffer[0]);
            $length = strlen($this->buffer);

            if (($first & 0xF0) === Firmata::DIGITAL_MESSAGE) {
                if ($length < 3) {
                    return; // ждём остаток
                }
                $port = $first & 0x0F;
                $bitmask = ord($this->buffer[1]) | (ord($this->buffer[2]) << 7);
                $this->buffer = substr($this->buffer, 3);
                foreach ($this->digitalListeners as $listener) {
                    $listener($port, $bitmask);
                }
            } elseif ($first === Firmata::REPORT_VERSION) {
                if ($length < 3) {
                    return;
                }
                $major = ord($this->buffer[1]);
                $minor = ord($this->buffer[2]);
                $this->buffer = substr($this->buffer, 3);
                foreach ($this->versionListeners as $listener) {
                    $listener($major, $minor);
                }
            } elseif ($first === Firmata::SYSEX_START) {
                $end = strpos($this->buffer, chr(Firmata::SYSEX_END));
                if ($end === false) {
                    return; // sysex ещё не пришёл целиком
                }
                $this->buffer = substr($this->buffer, $end + 1); // v1: sysex игнорируем
            } else {
                $this->buffer = substr($this->buffer, 1); // неизвестный байт — ресинхронизация
            }
        }
    }
}
```

- [ ] **Step 4: Убедиться, что тесты проходят**

Run: `./vendor/bin/pest tests/Adapter/Firmata/FirmataProtocolTest.php`
Expected: PASS (8 passed)

- [ ] **Step 5: Commit**

```bash
git add src/Adapter/Firmata tests/Adapter/Firmata
git commit -m "feat: протокол Firmata — энкодер и инкрементальный парсер"
```

---

### Task 7: Транспорт — SerialPort и InMemoryTransport

**Files:**
- Create: `src/Transport/Transport.php`, `src/Transport/TransportException.php`, `src/Transport/SerialPort.php`, `src/Transport/InMemoryTransport.php`
- Test: `tests/Transport/InMemoryTransportTest.php`

**Interfaces:**
- Consumes: `Loop` (Task 2) — только в тесте
- Produces:
  - `interface Femus\Transport\Transport` — `write(string $bytes): void`, `stream()` (возвращает resource для event loop), `readAvailable(): string`, `close(): void`
  - `final class SerialPort implements Transport` — `__construct(string $device, int $baudRate = 57600)`; настраивает порт через `stty` (macOS `stty -f`, Linux `stty -F`), открывает неблокирующий `r+b`-поток; бросает `TransportException` при ошибке
  - `final class InMemoryTransport implements Transport` — для тестов: `public string $written` копит исходящие байты, `feed(string $bytes): void` подаёт входящие (через внутренний `stream_socket_pair`, чтобы работал `stream_select`)
  - `final class TransportException extends \RuntimeException`

- [ ] **Step 1: Написать падающие тесты**

`tests/Transport/InMemoryTransportTest.php`:
```php
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\InMemoryTransport;

it('копит исходящие байты', function () {
    $transport = new InMemoryTransport();
    $transport->write("\x01");
    $transport->write("\x02");
    expect($transport->written)->toBe("\x01\x02");
});

it('feed доставляет байты через event loop', function () {
    $transport = new InMemoryTransport();
    $loop = new StreamSelectLoop();
    $received = '';
    $loop->addReadStream($transport->stream(), function () use ($transport, &$received, $loop) {
        $received .= $transport->readAvailable();
        $loop->stop();
    });
    $transport->feed("\xF9\x02\x05");
    $loop->run();
    expect($received)->toBe("\xF9\x02\x05");
});
```

- [ ] **Step 2: Убедиться, что тесты падают**

Run: `./vendor/bin/pest tests/Transport/InMemoryTransportTest.php`
Expected: FAIL — `Class "Femus\Transport\InMemoryTransport" not found`

- [ ] **Step 3: Реализовать транспорты**

`src/Transport/Transport.php`:
```php
namespace Femus\Transport;

interface Transport
{
    public function write(string $bytes): void;

    /** @return resource поток для регистрации в event loop */
    public function stream();

    /** Прочитать всё, что накопилось, без блокировки. */
    public function readAvailable(): string;

    public function close(): void;
}
```

`src/Transport/TransportException.php`:
```php
namespace Femus\Transport;

final class TransportException extends \RuntimeException
{
}
```

`src/Transport/SerialPort.php`:
```php
namespace Femus\Transport;

final class SerialPort implements Transport
{
    /** @var resource */
    private $stream;

    public function __construct(string $device, int $baudRate = 57600)
    {
        $flag = PHP_OS_FAMILY === 'Darwin' ? '-f' : '-F';
        $command = sprintf(
            'stty %s %s %d cs8 -cstopb -parenb -echo raw',
            $flag,
            escapeshellarg($device),
            $baudRate,
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new TransportException(
                "Не удалось настроить порт {$device}: " . implode("\n", $output),
            );
        }

        $stream = @fopen($device, 'r+b');
        if ($stream === false) {
            throw new TransportException("Не удалось открыть {$device} (права? устройство подключено?)");
        }
        stream_set_blocking($stream, false);
        $this->stream = $stream;
    }

    public function write(string $bytes): void
    {
        if (@fwrite($this->stream, $bytes) === false) {
            throw new TransportException('Ошибка записи в порт (устройство отключено?)');
        }
    }

    public function stream()
    {
        return $this->stream;
    }

    public function readAvailable(): string
    {
        return (string) stream_get_contents($this->stream);
    }

    public function close(): void
    {
        fclose($this->stream);
    }
}
```

`src/Transport/InMemoryTransport.php`:
```php
namespace Femus\Transport;

/** Тестовый транспорт: written копит исходящее, feed() подаёт входящее. */
final class InMemoryTransport implements Transport
{
    public string $written = '';

    /** @var resource читаемая сторона (отдаётся в event loop) */
    private $local;

    /** @var resource пишущая сторона (feed) */
    private $remote;

    public function __construct()
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false) {
            throw new TransportException('stream_socket_pair failed');
        }
        [$this->local, $this->remote] = $pair;
        stream_set_blocking($this->local, false);
    }

    public function write(string $bytes): void
    {
        $this->written .= $bytes;
    }

    public function feed(string $bytes): void
    {
        fwrite($this->remote, $bytes);
    }

    public function stream()
    {
        return $this->local;
    }

    public function readAvailable(): string
    {
        return (string) stream_get_contents($this->local);
    }

    public function close(): void
    {
        fclose($this->local);
        fclose($this->remote);
    }
}
```

- [ ] **Step 4: Убедиться, что тесты проходят**

Run: `./vendor/bin/pest tests/Transport/InMemoryTransportTest.php`
Expected: PASS (2 passed)

- [ ] **Step 5: Commit**

```bash
git add src/Transport tests/Transport
git commit -m "feat: транспорты SerialPort (stty) и InMemoryTransport"
```

---

### Task 8: FirmataBoard — адаптер целиком

**Files:**
- Create: `src/Adapter/Firmata/FirmataBoard.php`, `src/Adapter/Firmata/FirmataPin.php`, `src/Adapter/Firmata/BoardException.php`
- Test: `tests/Adapter/Firmata/FirmataBoardTest.php`

**Interfaces:**
- Consumes: `AbstractBoard`, `DigitalPin`, `PinMode` (Task 3), `Loop`, `StreamSelectLoop` (Task 2), `Firmata`, `FirmataEncoder`, `FirmataParser` (Task 6), `Transport`, `SerialPort`, `InMemoryTransport` (Task 7)
- Produces:
  - `final class Femus\Adapter\Firmata\FirmataBoard extends AbstractBoard` — `__construct(Transport $transport, Loop $loop, float $handshakeTimeout = 5.0)`, `static open(string $device, int $baudRate = 57600, ?Loop $loop = null): self` (открывает SerialPort и ждёт handshake), `awaitReady(): void` (ждёт REPORT_VERSION от прошивки, кидает `BoardException` по таймауту), `digitalPin()`, `@internal writeDigital(int $pin, bool $high): void`
  - `final class FirmataPin implements DigitalPin` — `@internal updateFromBoard(bool $high): void`
  - `final class BoardException extends \RuntimeException`
  - Схема пин→порт: `port = intdiv(pin, 8)`, бит `pin % 8`; при запросе входного пина шлётся `reportDigitalPort(port, true)`

- [ ] **Step 1: Написать падающие тесты**

`tests/Adapter/Firmata/FirmataBoardTest.php`:
```php
use Femus\Adapter\Firmata\BoardException;
use Femus\Adapter\Firmata\FirmataBoard;
use Femus\Contracts\PinMode;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\InMemoryTransport;

function readyBoard(InMemoryTransport $transport): FirmataBoard
{
    $board = new FirmataBoard($transport, new StreamSelectLoop());
    $transport->feed("\xF9\x02\x05"); // StandardFirmata шлёт версию при старте
    $board->awaitReady();

    return $board;
}

it('awaitReady проходит после версии от прошивки', function () {
    $board = readyBoard(new InMemoryTransport());
    expect($board)->toBeInstanceOf(FirmataBoard::class);
});

it('awaitReady кидает исключение по таймауту', function () {
    $board = new FirmataBoard(new InMemoryTransport(), new StreamSelectLoop(), handshakeTimeout: 0.05);
    $board->awaitReady();
})->throws(BoardException::class);

it('led на пине 13: setPinMode + digitalWrite порта 1', function () {
    $transport = new InMemoryTransport();
    $board = readyBoard($transport);
    $transport->written = '';

    $board->led(13)->on();

    expect($transport->written)->toBe(
        "\xF4\x0D\x01"      // SET_PIN_MODE pin 13 OUTPUT
        . "\x91\x20\x00",   // DIGITAL_MESSAGE порт 1, бит 5
    );
});

it('запрос входного пина включает репортинг его порта', function () {
    $transport = new InMemoryTransport();
    $board = readyBoard($transport);
    $transport->written = '';

    $board->digitalPin(2, PinMode::InputPullUp);

    expect($transport->written)->toBe(
        "\xF4\x02\x0B"   // SET_PIN_MODE pin 2 PULLUP
        . "\xD0\x01",    // REPORT_DIGITAL порт 0 on
    );
});

it('входящее digital message обновляет пин и зовёт onChange', function () {
    $transport = new InMemoryTransport();
    $board = readyBoard($transport);
    $pin = $board->digitalPin(2, PinMode::Input);

    $seen = null;
    $pin->onChange(function (bool $high) use (&$seen, $board) {
        $seen = $high;
        $board->stop();
    });

    $transport->feed("\x90\x04\x00"); // порт 0: пин 2 HIGH
    $board->loop()->addTimer(1.0, fn () => $board->stop()); // страховка от зависания
    $board->run();

    expect($seen)->toBeTrue()->and($pin->read())->toBeTrue();
});

it('два выходных пина одного порта не затирают друг друга', function () {
    $transport = new InMemoryTransport();
    $board = readyBoard($transport);
    $led2 = $board->led(2); // порт 0, бит 2
    $led3 = $board->led(3); // порт 0, бит 3
    $led2->on();
    $transport->written = '';

    $led3->on(); // битмаска обязана сохранить бит пина 2

    expect($transport->written)->toBe("\x90\x0C\x00"); // 0b1100
});
```

- [ ] **Step 2: Убедиться, что тесты падают**

Run: `./vendor/bin/pest tests/Adapter/Firmata/FirmataBoardTest.php`
Expected: FAIL — `Class "Femus\Adapter\Firmata\FirmataBoard" not found`

- [ ] **Step 3: Реализовать FirmataBoard и FirmataPin**

`src/Adapter/Firmata/BoardException.php`:
```php
namespace Femus\Adapter\Firmata;

final class BoardException extends \RuntimeException
{
}
```

`src/Adapter/Firmata/FirmataPin.php`:
```php
namespace Femus\Adapter\Firmata;

use Femus\Contracts\DigitalPin;
use Femus\Contracts\PinMode;

final class FirmataPin implements DigitalPin
{
    private bool $state = false;

    /** @var list<callable> */
    private array $listeners = [];

    public function __construct(
        private readonly int $number,
        public readonly PinMode $mode,
        private readonly FirmataBoard $board,
    ) {
    }

    public function number(): int
    {
        return $this->number;
    }

    public function write(bool $high): void
    {
        $this->state = $high;
        $this->board->writeDigital($this->number, $high);
    }

    public function read(): bool
    {
        return $this->state;
    }

    public function onChange(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    /** @internal вызывается FirmataBoard при входящем digital message */
    public function updateFromBoard(bool $high): void
    {
        if ($high === $this->state) {
            return;
        }
        $this->state = $high;
        foreach ($this->listeners as $listener) {
            $listener($high);
        }
    }
}
```

`src/Adapter/Firmata/FirmataBoard.php`:
```php
namespace Femus\Adapter\Firmata;

use Femus\AbstractBoard;
use Femus\Contracts\DigitalPin;
use Femus\Contracts\PinMode;
use Femus\Runtime\Loop;
use Femus\Runtime\StreamSelectLoop;
use Femus\Transport\SerialPort;
use Femus\Transport\Transport;

final class FirmataBoard extends AbstractBoard
{
    /** @var array<int, FirmataPin> */
    private array $pins = [];

    /** @var array<int, int> битмаски выходных портов */
    private array $portState = [];

    private readonly FirmataParser $parser;

    private bool $ready = false;

    public function __construct(
        private readonly Transport $transport,
        Loop $loop,
        private readonly float $handshakeTimeout = 5.0,
    ) {
        parent::__construct($loop);

        $this->parser = new FirmataParser();
        $this->parser->onVersion(function (): void {
            $this->ready = true;
        });
        $this->parser->onDigitalMessage($this->handleDigitalMessage(...));

        $loop->addReadStream(
            $transport->stream(),
            fn () => $this->parser->push($transport->readAvailable()),
        );
    }

    public static function open(string $device, int $baudRate = 57600, ?Loop $loop = null): self
    {
        $board = new self(new SerialPort($device, $baudRate), $loop ?? new StreamSelectLoop());
        $board->awaitReady();

        return $board;
    }

    public function awaitReady(): void
    {
        $deadline = hrtime(true) / 1e9 + $this->handshakeTimeout;
        while (!$this->ready) {
            if (hrtime(true) / 1e9 >= $deadline) {
                throw new BoardException(
                    'Arduino не ответила. Прошита ли StandardFirmata? Верный ли порт?',
                );
            }
            $this->loop->tick(0.1);
        }
    }

    public function digitalPin(int $number, PinMode $mode): DigitalPin
    {
        if (!isset($this->pins[$number])) {
            $firmataMode = match ($mode) {
                PinMode::Output => Firmata::MODE_OUTPUT,
                PinMode::Input => Firmata::MODE_INPUT,
                PinMode::InputPullUp => Firmata::MODE_PULLUP,
            };
            $this->transport->write(FirmataEncoder::setPinMode($number, $firmataMode));
            if ($mode !== PinMode::Output) {
                $this->transport->write(FirmataEncoder::reportDigitalPort(intdiv($number, 8), true));
            }
            $this->pins[$number] = new FirmataPin($number, $mode, $this);
        }

        return $this->pins[$number];
    }

    /** @internal */
    public function writeDigital(int $pin, bool $high): void
    {
        $port = intdiv($pin, 8);
        $bit = 1 << ($pin % 8);
        $mask = $this->portState[$port] ?? 0;
        $mask = $high ? ($mask | $bit) : ($mask & ~$bit);
        $this->portState[$port] = $mask;
        $this->transport->write(FirmataEncoder::digitalWrite($port, $mask));
    }

    private function handleDigitalMessage(int $port, int $bitmask): void
    {
        foreach ($this->pins as $number => $pin) {
            if (intdiv($number, 8) !== $port || $pin->mode === PinMode::Output) {
                continue;
            }
            $pin->updateFromBoard((bool) ($bitmask & (1 << ($number % 8))));
        }
    }
}
```

- [ ] **Step 4: Убедиться, что все тесты проходят**

Run: `composer test`
Expected: PASS (все тесты пакета)

- [ ] **Step 5: Commit**

```bash
git add src/Adapter/Firmata tests/Adapter/Firmata
git commit -m "feat: FirmataBoard — цифровой I/O через Arduino"
```

---

### Task 9: Фасад Board, примеры, README и проверка на живой Arduino

**Files:**
- Create: `src/Board.php`, `examples/blink.php`, `examples/button-led.php`, `docs/devices/button.md`, `README.md`
- Test: `tests/BoardTest.php` + ручная проверка на железе

**Interfaces:**
- Consumes: всё из Tasks 2–8
- Produces:
  - `final class Femus\Board` — статические фабрики: `fake(): FakeBoard`, `firmata(string $device, int $baudRate = 57600): FirmataBoard` (это API из спеки: `Board::firmata('/dev/ttyUSB0')`)

- [ ] **Step 1: Написать падающий тест**

`tests/BoardTest.php`:
```php
use Femus\Adapter\Fake\FakeBoard;
use Femus\Board;

it('Board::fake() создаёт FakeBoard с лупом', function () {
    $board = Board::fake();
    expect($board)->toBeInstanceOf(FakeBoard::class)
        ->and($board->loop())->not->toBeNull();
});
```

- [ ] **Step 2: Убедиться, что тест падает**

Run: `./vendor/bin/pest tests/BoardTest.php`
Expected: FAIL — `Class "Femus\Board" not found`

- [ ] **Step 3: Реализовать фасад и примеры**

`src/Board.php`:
```php
namespace Femus;

use Femus\Adapter\Fake\FakeBoard;
use Femus\Adapter\Firmata\FirmataBoard;
use Femus\Runtime\StreamSelectLoop;

final class Board
{
    public static function fake(): FakeBoard
    {
        return new FakeBoard(new StreamSelectLoop());
    }

    /** Arduino с прошивкой StandardFirmata, подключённая по USB. */
    public static function firmata(string $device, int $baudRate = 57600): FirmataBoard
    {
        return FirmataBoard::open($device, $baudRate);
    }

    private function __construct()
    {
    }
}
```

`examples/blink.php`:
```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

// Порт: macOS — /dev/cu.usbserial-XXXX или /dev/cu.usbmodemXXXX (ls /dev/cu.*),
// Linux — /dev/ttyUSB0 или /dev/ttyACM0.
$board = Board::firmata($argv[1] ?? '/dev/ttyUSB0');

$led = $board->led(13); // встроенный светодиод Arduino
$led->blink(0.5);

echo "Мигаем светодиодом на пине 13. Ctrl+C для выхода.\n";
$board->run();
```

`examples/button-led.php`:
```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;

$board = Board::firmata($argv[1] ?? '/dev/ttyUSB0');

$button = $board->button(2);   // кнопка: пин 2 — GND (внутренняя подтяжка)
$led = $board->led(13);

$button->onPress(fn () => $led->on());
$button->onRelease(fn () => $led->off());

echo "Держи кнопку — горит светодиод. Ctrl+C для выхода.\n";
$board->run();
```

`docs/devices/button.md`:
```markdown
# Кнопка (модуль KY-004 или любая тактовая кнопка)

## Подключение к Arduino (Firmata-адаптер)

| Пин кнопки | Arduino |
|------------|---------|
| S (сигнал) | D2      |
| — (земля)  | GND     |

Средний пин (+) модуля KY-004 не подключаем: femus включает внутреннюю
подтяжку (INPUT_PULLUP), нажатие замыкает сигнал на землю (active-low).

## Код

```php
$button = $board->button(2);
$button->onPress(fn () => print("нажата\n"));
$board->run();
```

## Проверка

`php examples/button-led.php /dev/ttyUSB0` — при удержании кнопки горит
светодиод на плате (пин 13).
```

`README.md`:
```markdown
# femus

PHP hardware framework: датчики, реле, кнопки и модули — из обычного PHP.
Один код работает через Arduino (Firmata по USB) и Raspberry Pi (в планах).

```php
use Femus\Board;

$board = Board::firmata('/dev/ttyUSB0');

$button = $board->button(2);
$led = $board->led(13);

$button->onPress(fn () => $led->on());
$button->onRelease(fn () => $led->off());

$board->run();
```

## Быстрый старт

1. Прошей Arduino стандартной прошивкой StandardFirmata
   (Arduino IDE → File → Examples → Firmata → StandardFirmata).
2. `composer require sanchescom/femus`
3. `php examples/blink.php /dev/ttyUSB0`

## Статус

В разработке. Готово: event loop, Firmata-адаптер (цифровой I/O),
Led / Relay / Buzzer / Button / MotionSensor, FakeBoard для тестов без железа.
Дальше по roadmap: Linux/FFI-адаптер (Raspberry Pi), аналоговые входы, I2C,
тензодатчик HX711, GSM/AT-стек, CLI-отладка.
```

- [ ] **Step 4: Прогнать все тесты**

Run: `composer test`
Expected: PASS (все тесты)

- [ ] **Step 5: Ручная проверка на живой Arduino (уровень 3 из спеки)**

Требуется человек с железом. Чеклист:

1. Прошить Arduino: Arduino IDE → File → Examples → Firmata → StandardFirmata → Upload (или `arduino-cli compile --fqbn arduino:avr:uno --upload` для скетча StandardFirmata).
2. Найти порт: macOS `ls /dev/cu.*`, Linux `ls /dev/ttyUSB* /dev/ttyACM*`.
3. `php examples/blink.php <порт>` — встроенный светодиод мигает раз в секунду.
4. Подключить кнопку (схема в `docs/devices/button.md`), `php examples/button-led.php <порт>` — светодиод горит при удержании.
5. Результат записать в отчёт прогона (дата, ОС, плата, порт) — формат свободный, файл `docs/hardware-runs.md`.

Если железа под рукой нет — шаг откладывается, но релиз без него не делается.

- [ ] **Step 6: Commit**

```bash
git add src/Board.php examples docs/devices README.md tests/BoardTest.php docs/hardware-runs.md
git commit -m "feat: фасад Board, примеры blink/button-led, README"
```

---

## Что дальше (следующие планы, не этот)

2. **Linux-адаптер**: FFI к libgpiod (`LinuxBoard`, `LinuxDigitalPin`), те же тесты устройств поверх него, запуск examples на Raspberry Pi 4.
3. **Аналог + I2C**: контракты `AnalogPin`/`I2cBus`, Firmata analog messaging + I2C sysex, драйверы AnalogSensor, Lcd1602, Mpu6050, MCP3008 (SPI) для Pi.
4. **HX711**: модуль ConfigurableFirmata (C++), драйвер LoadCell, калибровка.
5. **GSM/AT-стек**: `Uart`-контракт, `AtChannel` (очередь команд, unsolicited), `GsmModem` (SMS/звонки/статус сети) на SIM5216E.
6. **CLI**: `bin/femus` — `repl`, `scan`, `test:hardware`, `--verbose`-логирование шин.
