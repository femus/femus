# femus Port Discovery + Multi-Board — Implementation Plan (мини-план)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Steps use checkbox syntax.

**Goal:** Автообнаружение serial-портов (`Board::firmata()` без аргументов), общий event loop для нескольких плат в одном скрипте, пример two-boards.

**Architecture:** `SerialPortLocator` (glob-кандидаты по ОС, инжектируемый glob для тестов) в Femus\Transport; `Board::firmata(?string $device = null, int $baudRate = 57600, ?Loop $loop = null)` — при null перебирает кандидатов с коротким handshake-таймаутом. База: main (82 теста зелёные).

**Tech Stack:** PHP 8.2+, Pest. ВЕСЬ код на английском (комменты, исключения, it()-описания). Коммиты Conventional Commits на английском, без упоминаний Claude, без Co-Authored-By.

## Global Constraints

- Каждый PHP-файл: `<?php` + `declare(strict_types=1);`; классы final
- Существующие сигнатуры не ломать: `Board::firmata('/dev/x')` должен работать как раньше
- Докблоки только где типов не хватает (laravel-dev/rules/php.md)

---

### Task 1: SerialPortLocator + расширение фасада Board

**Files:**
- Create: `src/Transport/SerialPortLocator.php`
- Modify: `src/Board.php`
- Test: `tests/Transport/SerialPortLocatorTest.php`, дополнить `tests/BoardTest.php`

**Interfaces:**
- Produces:
  - `final class Femus\Transport\SerialPortLocator` — `__construct(?\Closure $glob = null)` (инжекция glob для тестов; null → системный glob); `candidates(): array` (list<string>: Darwin → `/dev/cu.usbserial*`, `/dev/cu.usbmodem*`, `/dev/cu.wchusbserial*`, `/dev/cu.*` c фильтром-исключением подстрок `Bluetooth-Incoming`, `debug`, `wlan`; Linux → `/dev/ttyUSB*`, `/dev/ttyACM*`, `/dev/rfcomm*`; без дублей, отсортировано)
  - `Board::firmata(?string $device = null, int $baudRate = 57600, ?Loop $loop = null): FirmataBoard` — при $device === null: перебирает `SerialPortLocator::candidates()`, для каждого пробует `new FirmataBoard(new SerialPort($port, $baudRate), $loop ?? new StreamSelectLoop(), handshakeTimeout: 3.0)` + `awaitReady()`, ловит `TransportException|BoardException` и идёт дальше; ни один не ответил → `BoardException` с сообщением `'No Firmata board found. Probed ports: ...'` (или `'No serial ports found...'` если кандидатов ноль). При явном $device — прежнее поведение (FirmataBoard::open с пробросом $loop)
  - ВНИМАНИЕ: при null и явном $loop каждая неудачная проба регистрирует read stream в общем лупе — обязательно снимать: при провале пробы вызвать `$loop->removeReadStream($transport->stream())` и `$transport->close()`. Для этого пробу строить руками через SerialPort (транспорт доступен), а не FirmataBoard::open

- [ ] **Step 1: Падающие тесты**

`tests/Transport/SerialPortLocatorTest.php`:
```php
use Femus\Transport\SerialPortLocator;

it('lists usb serial candidates on this OS', function () {
    $fakeGlob = function (string $pattern): array {
        return match (true) {
            str_contains($pattern, 'usbserial') => ['/dev/cu.usbserial-1420'],
            str_contains($pattern, 'ttyUSB') => ['/dev/ttyUSB0'],
            default => [],
        };
    };
    $locator = new SerialPortLocator($fakeGlob(...));
    $expected = PHP_OS_FAMILY === 'Darwin' ? '/dev/cu.usbserial-1420' : '/dev/ttyUSB0';
    expect($locator->candidates())->toContain($expected);
});

it('excludes noise ports and deduplicates', function () {
    $fakeGlob = fn (string $pattern): array => str_contains($pattern, 'cu.*') || str_contains($pattern, 'usbserial')
        ? ['/dev/cu.Bluetooth-Incoming-Port', '/dev/cu.debug-console', '/dev/cu.usbserial-1420', '/dev/cu.HC-05-DevB', '/dev/cu.usbserial-1420']
        : [];
    $locator = new SerialPortLocator($fakeGlob(...));
    $candidates = $locator->candidates();
    expect($candidates)->not->toContain('/dev/cu.Bluetooth-Incoming-Port')
        ->and($candidates)->not->toContain('/dev/cu.debug-console');
    if (PHP_OS_FAMILY === 'Darwin') {
        expect($candidates)->toContain('/dev/cu.HC-05-DevB')
            ->and(array_count_values($candidates)['/dev/cu.usbserial-1420'])->toBe(1);
    }
});
```

Дополнить `tests/BoardTest.php`:
```php
use Femus\Adapter\Firmata\BoardException;

it('auto-detection throws a clear error when no ports exist', function () {
    // machine-independent only if no real board is attached; probing real ports
    // must not crash — accept either exception message or (rare) success skip
    try {
        Femus\Board::firmata();
        expect(true)->toBeTrue(); // a real board answered — environment-dependent, fine
    } catch (BoardException $e) {
        expect($e->getMessage())->toContain('No');
    }
})->skipOnWindows();
```

- [ ] **Step 2: Прогнать — падают** (`Class SerialPortLocator not found`)

- [ ] **Step 3: Реализация** (код по Interfaces выше; candidates(): собрать по паттернам, отфильтровать исключения-подстроки, array_values(array_unique()), sort)

- [ ] **Step 4: `composer test` — зелёные**

- [ ] **Step 5: Commit** `feat: serial port auto-discovery and shared loop in Board facade`

---

### Task 2: Пример two-boards + README

**Files:**
- Create: `examples/two-boards.php`
- Modify: `README.md` (короткие секции Port discovery + Multiple boards после Quick start)

`examples/two-boards.php` (английский):
```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Femus\Board;
use Femus\Runtime\StreamSelectLoop;

// Two boards, one PHP process: a button on board A drives the LED on board B.
// Usage: php examples/two-boards.php /dev/cu.usbserial-A /dev/cu.usbserial-B
$loop = new StreamSelectLoop();
$boardA = Board::firmata($argv[1] ?? null, loop: $loop);
$boardB = Board::firmata($argv[2] ?? throw new InvalidArgumentException('Pass two ports'), loop: $loop);

$button = $boardA->button(2);
$led = $boardB->led(13);

$button->onPress(fn () => $led->on());
$button->onRelease(fn () => $led->off());

echo "Hold the button on board A — the LED on board B lights up. Ctrl+C to exit.\n";
$loop->run();
```

README additions (English): "Port discovery" — `Board::firmata()` with no arguments probes serial ports and finds the first Firmata board (`ls /dev/cu.*` / `ls /dev/ttyUSB*` to see ports manually); "Multiple boards" — pass a shared `StreamSelectLoop` via `loop:` to drive several boards from one script (5-line snippet).

- [ ] Steps: написать, `php -l`, `composer test`, commit `docs: two-boards example and port discovery notes`
