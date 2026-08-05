# femus firmware:flash + Configurable RadioBleBridge — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a `femus firmware:flash` CLI that compiles and uploads bundled Arduino sketches via arduino-cli, and make RadioBleBridge configure its node/peer addresses over BLE with EEPROM persistence so one hex fits every user.

**Architecture:** A thin dependency-free CLI (`bin/femus` → `Femus\Cli\Application`) dispatches `firmware:flash`. Process execution hides behind a `CommandRunner` interface so orchestration is unit-testable with a fake; the real runner streams arduino-cli output. The bridge sketch reads node/peer from EEPROM and intercepts slash-prefixed BLE lines as config commands.

**Tech Stack:** PHP 8.2 (no console framework — hand-rolled router), Pest v3 for tests, arduino-cli as the compile/upload backend, Arduino C++ (RH_ASK, EEPROM) for the bridge.

## Global Constraints

- `declare(strict_types=1);` at the top of every PHP file.
- `final` classes; `readonly` promoted constructor properties where the value is set once.
- No new production dependencies in `composer.json` (only `pestphp/pest` in dev stays).
- PHP floor `^8.2` (per `composer.json`). `new` in default parameter values is allowed.
- Tests are Pest `it(...)`/`expect(...)` style, files suffixed `Test.php` under `tests/`.
- Never invoke a real flash in tests — use the fake `CommandRunner`.
- Default Nano FQBN: `arduino:avr:nano:cpu=atmega328old` (owner's board is Old Bootloader).
- Firmware has no unit tests in this project; validate sketches with `arduino-cli compile`.
- Autoload: `Femus\` → `src/`, `Femus\Tests\` → `tests/` (already configured).

---

### Task 1: Configurable RadioBleBridge (firmware + docs)

Replace the hardcoded `#define NODE_ADDRESS/PEER_ADDRESS` with EEPROM-backed globals and intercept `/addr`, `/peer`, `/show` BLE commands. No PHP tests — verified by `arduino-cli compile`.

**Files:**
- Modify: `firmware/RadioBleBridge/RadioBleBridge.ino` (full rewrite)
- Modify: `docs/devices/radio-ble-bridge.md` (document commands + EEPROM)

**Interfaces:**
- Produces: EEPROM layout — byte 0 = MAGIC `0xF3`, byte 1 = nodeAddr, byte 2 = peerAddr. BLE command protocol: `/addr N`, `/peer N`, `/show`; acks `ok addr=N` / `ok peer=N` / `addr=N peer=M` / `err unknown`.

- [ ] **Step 1: Rewrite the sketch**

Replace the entire contents of `firmware/RadioBleBridge/RadioBleBridge.ino` with:

```cpp
/*
 * femus RadioBleBridge — phone node of the radio messenger.
 * iPhone (BLE, HM-10) <-> 433 MHz ASK radio (FS1000A + MX-RM-5V).
 * No Firmata here: this node is a dumb line-oriented bridge.
 *
 * Node/peer addresses are configured over BLE and kept in EEPROM:
 *   /addr N   set this node's address (0-255)
 *   /peer N   set the peer's address (0-255)
 *   /show     print current addresses
 * Slash-lines are handled locally and never sent over the radio.
 */

#include <RH_ASK.h>
#include <SoftwareSerial.h>
#include <EEPROM.h>

#define EEPROM_MAGIC 0xF3
#define ADDR_MAGIC   0
#define ADDR_NODE    1
#define ADDR_PEER    2
#define DEFAULT_NODE 2
#define DEFAULT_PEER 1

SoftwareSerial ble(7, 8);      // D7 <- HM-10 TXD, D8 -> HM-10 RXD (via 1k/2k divider)
RH_ASK radio(2000, 11, 12);    // rxPin D11 (MX-RM-5V DATA), txPin D12 (FS1000A DATA)

byte nodeAddr;
byte peerAddr;

char lineBuf[51];
byte lineLen = 0;

void loadConfig()
{
  if (EEPROM.read(ADDR_MAGIC) != EEPROM_MAGIC) {
    EEPROM.update(ADDR_NODE, DEFAULT_NODE);
    EEPROM.update(ADDR_PEER, DEFAULT_PEER);
    EEPROM.update(ADDR_MAGIC, EEPROM_MAGIC);
  }
  nodeAddr = EEPROM.read(ADDR_NODE);
  peerAddr = EEPROM.read(ADDR_PEER);
}

void handleCommand()
{
  // lineBuf is NUL-terminated by the caller.
  if (strncmp(lineBuf, "/addr ", 6) == 0) {
    nodeAddr = (byte) atoi(lineBuf + 6);
    EEPROM.update(ADDR_NODE, nodeAddr);
    radio.setThisAddress(nodeAddr);
    ble.print("ok addr=");
    ble.println(nodeAddr);
  } else if (strncmp(lineBuf, "/peer ", 6) == 0) {
    peerAddr = (byte) atoi(lineBuf + 6);
    EEPROM.update(ADDR_PEER, peerAddr);
    ble.print("ok peer=");
    ble.println(peerAddr);
  } else if (strcmp(lineBuf, "/show") == 0) {
    ble.print("addr=");
    ble.print(nodeAddr);
    ble.print(" peer=");
    ble.println(peerAddr);
  } else {
    ble.println("err unknown");
  }
}

void setup()
{
  ble.begin(9600);
  radio.init();
  loadConfig();
  radio.setThisAddress(nodeAddr);
}

void loop()
{
  while (ble.available()) {
    char c = ble.read();
    if (c == '\n' || c == '\r') {
      if (lineLen > 0) {
        lineBuf[lineLen] = '\0';
        if (lineBuf[0] == '/') {
          handleCommand();
        } else {
          radio.setHeaderFrom(nodeAddr);
          radio.setHeaderTo(peerAddr);
          radio.send((uint8_t *) lineBuf, lineLen);
          radio.waitPacketSent();
        }
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

- [ ] **Step 2: Ensure toolchain, then compile-verify**

Run (one-time toolchain prep is idempotent):
```bash
arduino-cli core install arduino:avr
arduino-cli lib install RadioHead
arduino-cli compile --fqbn arduino:avr:nano:cpu=atmega328old firmware/RadioBleBridge
```
Expected: `Compilation ... completed`, exit 0.

- [ ] **Step 3: Document commands in the device guide**

In `docs/devices/radio-ble-bridge.md`, add a section describing the config commands and EEPROM behavior:

```markdown
## Конфигурация адресов (по BLE)

Адреса узлов не зашиты в код — задаются с телефона и хранятся в EEPROM
(переживают перезагрузку). Строки, начинающиеся с `/`, перехватываются мостом
и НЕ уходят в радио:

- `/addr N` — свой адрес узла (0–255), ответ `ok addr=N`
- `/peer N` — адрес собеседника (0–255), ответ `ok peer=N`
- `/show` — текущие адреса, ответ `addr=N peer=M`
- неизвестная `/…` — ответ `err unknown`

При чистом EEPROM применяются дефолты node=2, peer=1 (телефонный узел
мессенджера), поэтому незалитый настройками мост сразу работает со станцией.
Формат EEPROM: байт 0 — маркер `0xF3`, байт 1 — node, байт 2 — peer.
```

- [ ] **Step 4: Commit**

```bash
git add firmware/RadioBleBridge/RadioBleBridge.ino docs/devices/radio-ble-bridge.md
git commit -m "feat: конфигурируемые адреса RadioBleBridge через BLE + EEPROM"
```

---

### Task 2: Process execution layer

A `CommandRunner` interface with a real (`SystemCommandRunner`) and a test-only fake implementation, plus a `CommandResult` value.

**Files:**
- Create: `src/Cli/Process/CommandResult.php`
- Create: `src/Cli/Process/CommandRunner.php`
- Create: `src/Cli/Process/SystemCommandRunner.php`
- Create: `tests/Cli/FakeCommandRunner.php` (test helper, not a test file)
- Test: `tests/Cli/Process/SystemCommandRunnerTest.php`

**Interfaces:**
- Produces:
  - `Femus\Cli\Process\CommandResult` — `__construct(int $exitCode, string $output = '')`, public readonly `$exitCode`/`$output`, `succeeded(): bool`.
  - `Femus\Cli\Process\CommandRunner` — `run(array $argv): CommandResult` (`$argv` is `list<string>`).
  - `Femus\Cli\Process\SystemCommandRunner implements CommandRunner`.
  - `Femus\Tests\Cli\FakeCommandRunner implements CommandRunner` — records `array $calls` (list of argv), returns per-index `$results` or a `$default`.

- [ ] **Step 1: Write CommandResult**

`src/Cli/Process/CommandResult.php`:
```php
<?php

declare(strict_types=1);

namespace Femus\Cli\Process;

final class CommandResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output = '',
    ) {
    }

    public function succeeded(): bool
    {
        return $this->exitCode === 0;
    }
}
```

- [ ] **Step 2: Write CommandRunner interface**

`src/Cli/Process/CommandRunner.php`:
```php
<?php

declare(strict_types=1);

namespace Femus\Cli\Process;

interface CommandRunner
{
    /** @param list<string> $argv */
    public function run(array $argv): CommandResult;
}
```

- [ ] **Step 3: Write the failing test for SystemCommandRunner**

`tests/Cli/Process/SystemCommandRunnerTest.php`:
```php
<?php

declare(strict_types=1);

use Femus\Cli\Process\SystemCommandRunner;

it('runs a command, captures output and a zero exit code', function () {
    $result = (new SystemCommandRunner())->run(['printf', 'hello']);
    expect($result->succeeded())->toBeTrue()
        ->and($result->output)->toContain('hello');
});

it('reports a non-zero exit code for a failing command', function () {
    $result = (new SystemCommandRunner())->run(['false']);
    expect($result->succeeded())->toBeFalse();
});
```

- [ ] **Step 4: Run it to confirm it fails**

Run: `vendor/bin/pest tests/Cli/Process/SystemCommandRunnerTest.php`
Expected: FAIL — class `SystemCommandRunner` not found.

- [ ] **Step 5: Write SystemCommandRunner**

`src/Cli/Process/SystemCommandRunner.php`:
```php
<?php

declare(strict_types=1);

namespace Femus\Cli\Process;

final class SystemCommandRunner implements CommandRunner
{
    public function run(array $argv): CommandResult
    {
        $command = implode(' ', array_map('escapeshellarg', $argv));
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return new CommandResult(127);
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        while (!feof($pipes[1]) || !feof($pipes[2])) {
            $read = [$pipes[1], $pipes[2]];
            $write = $except = [];
            if (stream_select($read, $write, $except, 1) === false) {
                break;
            }
            foreach ($read as $pipe) {
                $chunk = fread($pipe, 4096);
                if ($chunk !== false && $chunk !== '') {
                    fwrite(STDOUT, $chunk);
                    $output .= $chunk;
                }
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        return new CommandResult(proc_close($process), $output);
    }
}
```

- [ ] **Step 6: Run the test to confirm it passes**

Run: `vendor/bin/pest tests/Cli/Process/SystemCommandRunnerTest.php`
Expected: PASS (2 tests).

- [ ] **Step 7: Write the fake runner test helper**

`tests/Cli/FakeCommandRunner.php`:
```php
<?php

declare(strict_types=1);

namespace Femus\Tests\Cli;

use Femus\Cli\Process\CommandResult;
use Femus\Cli\Process\CommandRunner;

final class FakeCommandRunner implements CommandRunner
{
    /** @var list<list<string>> */
    public array $calls = [];

    /** @param array<int, CommandResult> $results keyed by call index */
    public function __construct(
        private readonly CommandResult $default = new CommandResult(0),
        private readonly array $results = [],
    ) {
    }

    public function run(array $argv): CommandResult
    {
        $index = count($this->calls);
        $this->calls[] = $argv;

        return $this->results[$index] ?? $this->default;
    }
}
```

- [ ] **Step 8: Commit**

```bash
git add src/Cli/Process tests/Cli
git commit -m "feat: CommandRunner process layer for the femus CLI"
```

---

### Task 3: ArduinoCli wrapper

Wrap arduino-cli subcommands as argv builders delegating to a `CommandRunner`.

**Files:**
- Create: `src/Cli/Arduino/ArduinoCli.php`
- Test: `tests/Cli/Arduino/ArduinoCliTest.php`

**Interfaces:**
- Consumes: `Femus\Cli\Process\CommandRunner`, `Femus\Cli\Process\CommandResult`.
- Produces: `Femus\Cli\Arduino\ArduinoCli` — `__construct(CommandRunner $runner, string $binary = 'arduino-cli')`; `isAvailable(): bool`; `coreInstall(string $core): CommandResult`; `libInstall(string $lib): CommandResult`; `compileAndUpload(string $sketchDir, string $fqbn, string $port): CommandResult`.

- [ ] **Step 1: Write the failing test**

`tests/Cli/Arduino/ArduinoCliTest.php`:
```php
<?php

declare(strict_types=1);

use Femus\Cli\Arduino\ArduinoCli;
use Femus\Cli\Process\CommandResult;
use Femus\Tests\Cli\FakeCommandRunner;

it('reports availability from the version subcommand exit code', function () {
    $ok = new ArduinoCli(new FakeCommandRunner(new CommandResult(0)));
    $missing = new ArduinoCli(new FakeCommandRunner(new CommandResult(127)));
    expect($ok->isAvailable())->toBeTrue()
        ->and($missing->isAvailable())->toBeFalse();
});

it('builds the compile-and-upload argv', function () {
    $runner = new FakeCommandRunner();
    (new ArduinoCli($runner))->compileAndUpload('firmware/RadioBleBridge', 'arduino:avr:nano:cpu=atmega328old', '/dev/cu.usbserial-1420');
    expect($runner->calls[0])->toBe([
        'arduino-cli', 'compile',
        '--fqbn', 'arduino:avr:nano:cpu=atmega328old',
        '--upload',
        '-p', '/dev/cu.usbserial-1420',
        'firmware/RadioBleBridge',
    ]);
});

it('builds core and lib install argv', function () {
    $runner = new FakeCommandRunner();
    $cli = new ArduinoCli($runner);
    $cli->coreInstall('arduino:avr');
    $cli->libInstall('RadioHead');
    expect($runner->calls[0])->toBe(['arduino-cli', 'core', 'install', 'arduino:avr'])
        ->and($runner->calls[1])->toBe(['arduino-cli', 'lib', 'install', 'RadioHead']);
});
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `vendor/bin/pest tests/Cli/Arduino/ArduinoCliTest.php`
Expected: FAIL — class `ArduinoCli` not found.

- [ ] **Step 3: Write ArduinoCli**

`src/Cli/Arduino/ArduinoCli.php`:
```php
<?php

declare(strict_types=1);

namespace Femus\Cli\Arduino;

use Femus\Cli\Process\CommandResult;
use Femus\Cli\Process\CommandRunner;

final class ArduinoCli
{
    public function __construct(
        private readonly CommandRunner $runner,
        private readonly string $binary = 'arduino-cli',
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->runner->run([$this->binary, 'version'])->succeeded();
    }

    public function coreInstall(string $core): CommandResult
    {
        return $this->runner->run([$this->binary, 'core', 'install', $core]);
    }

    public function libInstall(string $lib): CommandResult
    {
        return $this->runner->run([$this->binary, 'lib', 'install', $lib]);
    }

    public function compileAndUpload(string $sketchDir, string $fqbn, string $port): CommandResult
    {
        return $this->runner->run([
            $this->binary, 'compile',
            '--fqbn', $fqbn,
            '--upload',
            '-p', $port,
            $sketchDir,
        ]);
    }
}
```

- [ ] **Step 4: Run the test to confirm it passes**

Run: `vendor/bin/pest tests/Cli/Arduino/ArduinoCliTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Cli/Arduino tests/Cli/Arduino
git commit -m "feat: ArduinoCli wrapper over arduino-cli subcommands"
```

---

### Task 4: Flash argument parser

Parse `firmware:flash` positional target and `--key=value` options into a value object.

**Files:**
- Create: `src/Cli/Command/FlashOptions.php`
- Test: `tests/Cli/Command/FlashOptionsTest.php`

**Interfaces:**
- Produces: `Femus\Cli\Command\FlashOptions` — public readonly `?string $target`, `array<string,string> $options`; static `parse(array $args): self` (`$args` is `list<string>` — the argv slice after `firmware:flash`).

- [ ] **Step 1: Write the failing test**

`tests/Cli/Command/FlashOptionsTest.php`:
```php
<?php

declare(strict_types=1);

use Femus\Cli\Command\FlashOptions;

it('parses a target with options', function () {
    $parsed = FlashOptions::parse(['radio-bridge', '--port=/dev/cu.usbserial-1420', '--fqbn=arduino:avr:nano']);
    expect($parsed->target)->toBe('radio-bridge')
        ->and($parsed->options)->toBe([
            'port' => '/dev/cu.usbserial-1420',
            'fqbn' => 'arduino:avr:nano',
        ]);
});

it('leaves target null and options empty when absent', function () {
    $parsed = FlashOptions::parse([]);
    expect($parsed->target)->toBeNull()
        ->and($parsed->options)->toBe([]);
});

it('accepts options before the target', function () {
    $parsed = FlashOptions::parse(['--port=auto', 'femus']);
    expect($parsed->target)->toBe('femus')
        ->and($parsed->options)->toBe(['port' => 'auto']);
});
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `vendor/bin/pest tests/Cli/Command/FlashOptionsTest.php`
Expected: FAIL — class `FlashOptions` not found.

- [ ] **Step 3: Write FlashOptions**

`src/Cli/Command/FlashOptions.php`:
```php
<?php

declare(strict_types=1);

namespace Femus\Cli\Command;

final class FlashOptions
{
    /** @param array<string, string> $options */
    public function __construct(
        public readonly ?string $target,
        public readonly array $options,
    ) {
    }

    /** @param list<string> $args */
    public static function parse(array $args): self
    {
        $target = null;
        $options = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, '');
                $options[$key] = $value;
            } elseif ($target === null) {
                $target = $arg;
            }
        }

        return new self($target, $options);
    }
}
```

- [ ] **Step 4: Run the test to confirm it passes**

Run: `vendor/bin/pest tests/Cli/Command/FlashOptionsTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Cli/Command/FlashOptions.php tests/Cli/Command/FlashOptionsTest.php
git commit -m "feat: FlashOptions argument parser for firmware:flash"
```

---

### Task 5: FlashFirmware command

Orchestrate: validate target, check arduino-cli, install core+libs, resolve port, compile+upload.

**Files:**
- Create: `src/Cli/Command/FlashFirmware.php`
- Test: `tests/Cli/Command/FlashFirmwareTest.php`

**Interfaces:**
- Consumes: `Femus\Cli\Arduino\ArduinoCli`, `Femus\Transport\SerialPortLocator`, `Femus\Cli\Process\CommandResult`.
- Produces: `Femus\Cli\Command\FlashFirmware` — `__construct(ArduinoCli $arduino, SerialPortLocator $locator, string $projectRoot)`; `run(?string $target, array $options, callable $out): int` where `$options` is `array<string,string>` and `$out` is `callable(string): void`; returns a process exit code (0 ok, 1 failure, 2 usage). Targets: `femus` → `firmware/FemusFirmata` (libs `ConfigurableFirmata`, `RadioHead`, `HX711`); `radio-bridge` → `firmware/RadioBleBridge` (lib `RadioHead`). Default FQBN `arduino:avr:nano:cpu=atmega328old`. `--port` defaults to `auto` (uses `SerialPortLocator::candidates()`, first match).

- [ ] **Step 1: Write the failing test**

`tests/Cli/Command/FlashFirmwareTest.php`:
```php
<?php

declare(strict_types=1);

use Femus\Cli\Arduino\ArduinoCli;
use Femus\Cli\Command\FlashFirmware;
use Femus\Cli\Process\CommandResult;
use Femus\Tests\Cli\FakeCommandRunner;
use Femus\Transport\SerialPortLocator;

function makeFlash(FakeCommandRunner $runner, array $ports): FlashFirmware
{
    $locator = new SerialPortLocator(fn (string $pattern): array => $ports);

    return new FlashFirmware(new ArduinoCli($runner), $locator, '/project');
}

function sink(): array
{
    return [[], static function (string $line) use (&$lines): void {
        $lines[] = $line;
    }];
}

it('rejects an unknown target with usage exit code 2', function () {
    $runner = new FakeCommandRunner();
    $lines = [];
    $out = static function (string $line) use (&$lines): void {
        $lines[] = $line;
    };
    $code = makeFlash($runner, [])->run('nope', [], $out);
    expect($code)->toBe(2)
        ->and($runner->calls)->toBe([]);
    expect(implode("\n", $lines))->toContain('usage');
});

it('fails with exit code 1 when arduino-cli is missing', function () {
    $runner = new FakeCommandRunner(new CommandResult(127));
    $code = makeFlash($runner, [])->run('radio-bridge', [], static fn (string $l) => null);
    expect($code)->toBe(1)
        ->and($runner->calls[0])->toBe(['arduino-cli', 'version']);
});

it('installs deps, resolves the auto port and flashes radio-bridge', function () {
    $runner = new FakeCommandRunner();
    $ports = PHP_OS_FAMILY === 'Darwin' ? ['/dev/cu.usbserial-1420'] : ['/dev/ttyUSB0'];
    $code = makeFlash($runner, $ports)->run('radio-bridge', [], static fn (string $l) => null);

    $expectedPort = $ports[0];
    expect($code)->toBe(0);
    expect($runner->calls)->toContain(['arduino-cli', 'core', 'install', 'arduino:avr']);
    expect($runner->calls)->toContain(['arduino-cli', 'lib', 'install', 'RadioHead']);
    expect(end($runner->calls))->toBe([
        'arduino-cli', 'compile',
        '--fqbn', 'arduino:avr:nano:cpu=atmega328old',
        '--upload',
        '-p', $expectedPort,
        '/project/firmware/RadioBleBridge',
    ]);
});

it('fails when no board is found on auto port', function () {
    $runner = new FakeCommandRunner();
    $code = makeFlash($runner, [])->run('femus', ['port' => 'auto'], static fn (string $l) => null);
    expect($code)->toBe(1);
    // compile must not have run
    foreach ($runner->calls as $call) {
        expect($call[1] ?? '')->not->toBe('compile');
    }
});

it('honors an explicit --fqbn and --port', function () {
    $runner = new FakeCommandRunner();
    $code = makeFlash($runner, [])->run('femus', ['port' => '/dev/ttyUSB9', 'fqbn' => 'arduino:avr:nano'], static fn (string $l) => null);
    expect($code)->toBe(0);
    expect(end($runner->calls))->toBe([
        'arduino-cli', 'compile',
        '--fqbn', 'arduino:avr:nano',
        '--upload',
        '-p', '/dev/ttyUSB9',
        '/project/firmware/FemusFirmata',
    ]);
});
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `vendor/bin/pest tests/Cli/Command/FlashFirmwareTest.php`
Expected: FAIL — class `FlashFirmware` not found.

- [ ] **Step 3: Write FlashFirmware**

`src/Cli/Command/FlashFirmware.php`:
```php
<?php

declare(strict_types=1);

namespace Femus\Cli\Command;

use Femus\Cli\Arduino\ArduinoCli;
use Femus\Transport\SerialPortLocator;

final class FlashFirmware
{
    private const DEFAULT_FQBN = 'arduino:avr:nano:cpu=atmega328old';

    /** @var array<string, array{dir: string, libs: list<string>}> */
    private const TARGETS = [
        'femus' => [
            'dir' => 'firmware/FemusFirmata',
            'libs' => ['ConfigurableFirmata', 'RadioHead', 'HX711'],
        ],
        'radio-bridge' => [
            'dir' => 'firmware/RadioBleBridge',
            'libs' => ['RadioHead'],
        ],
    ];

    public function __construct(
        private readonly ArduinoCli $arduino,
        private readonly SerialPortLocator $locator,
        private readonly string $projectRoot,
    ) {
    }

    /**
     * @param array<string, string> $options
     * @param callable(string): void $out
     */
    public function run(?string $target, array $options, callable $out): int
    {
        if ($target === null || !isset(self::TARGETS[$target])) {
            $out('usage: femus firmware:flash <femus|radio-bridge> [--port=auto] [--fqbn=...]');

            return 2;
        }

        if (!$this->arduino->isAvailable()) {
            $out('arduino-cli not found. Install it: https://arduino.github.io/arduino-cli/latest/installation/');

            return 1;
        }

        $spec = self::TARGETS[$target];
        $fqbn = $options['fqbn'] ?? self::DEFAULT_FQBN;

        $port = $options['port'] ?? 'auto';
        if ($port === 'auto') {
            $candidates = $this->locator->candidates();
            if ($candidates === []) {
                $out('No board found. Connect it or pass --port=/dev/...');

                return 1;
            }
            $port = $candidates[0];
        }
        $out("Port: {$port}");

        if (!$this->arduino->coreInstall('arduino:avr')->succeeded()) {
            $out('Failed to install core arduino:avr.');

            return 1;
        }

        foreach ($spec['libs'] as $lib) {
            if (!$this->arduino->libInstall($lib)->succeeded()) {
                $out("Failed to install library: {$lib}");

                return 1;
            }
        }

        $sketchDir = $this->projectRoot . '/' . $spec['dir'];
        $out("Flashing {$target} ({$fqbn}) ...");

        if (!$this->arduino->compileAndUpload($sketchDir, $fqbn, $port)->succeeded()) {
            $out('Flash failed.');

            return 1;
        }

        $out('Done.');

        return 0;
    }
}
```

- [ ] **Step 4: Run the test to confirm it passes**

Run: `vendor/bin/pest tests/Cli/Command/FlashFirmwareTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Cli/Command/FlashFirmware.php tests/Cli/Command/FlashFirmwareTest.php
git commit -m "feat: FlashFirmware command orchestrating arduino-cli flash"
```

---

### Task 6: Application router + bin/femus entry point

Wire a top-level CLI that dispatches `firmware:flash`, register the binary in composer.

**Files:**
- Create: `src/Cli/Application.php`
- Create: `bin/femus`
- Modify: `composer.json` (add `"bin"` entry)
- Test: `tests/Cli/ApplicationTest.php`

**Interfaces:**
- Consumes: `Femus\Cli\Command\FlashOptions`, `Femus\Cli\Command\FlashFirmware`, `Femus\Cli\Arduino\ArduinoCli`, `Femus\Cli\Process\SystemCommandRunner`, `Femus\Transport\SerialPortLocator`.
- Produces: `Femus\Cli\Application` — `__construct(string $projectRoot)`; `run(array $argv, callable $out): int` where `$argv` is the full argv (`$argv[0]` = script). Unknown command prints usage → exit 2; no command → usage → exit 0.

- [ ] **Step 1: Write the failing test**

`tests/Cli/ApplicationTest.php`:
```php
<?php

declare(strict_types=1);

use Femus\Cli\Application;

it('prints usage and exits 0 with no command', function () {
    $lines = [];
    $code = (new Application('/project'))->run(['femus'], static function (string $l) use (&$lines): void {
        $lines[] = $l;
    });
    expect($code)->toBe(0)
        ->and(implode("\n", $lines))->toContain('firmware:flash');
});

it('exits 2 for an unknown command', function () {
    $code = (new Application('/project'))->run(['femus', 'bogus'], static fn (string $l) => null);
    expect($code)->toBe(2);
});
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `vendor/bin/pest tests/Cli/ApplicationTest.php`
Expected: FAIL — class `Application` not found.

- [ ] **Step 3: Write Application**

`src/Cli/Application.php`:
```php
<?php

declare(strict_types=1);

namespace Femus\Cli;

use Femus\Cli\Arduino\ArduinoCli;
use Femus\Cli\Command\FlashFirmware;
use Femus\Cli\Command\FlashOptions;
use Femus\Cli\Process\SystemCommandRunner;
use Femus\Transport\SerialPortLocator;

final class Application
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @param list<string> $argv full argv, script name at [0]
     * @param callable(string): void $out
     */
    public function run(array $argv, callable $out): int
    {
        $command = $argv[1] ?? null;

        if ($command === 'firmware:flash') {
            $parsed = FlashOptions::parse(array_slice($argv, 2));
            $flash = new FlashFirmware(
                new ArduinoCli(new SystemCommandRunner()),
                new SerialPortLocator(),
                $this->projectRoot,
            );

            return $flash->run($parsed->target, $parsed->options, $out);
        }

        $out('femus — PHP hardware framework CLI');
        $out('usage: femus firmware:flash <femus|radio-bridge> [--port=auto] [--fqbn=...]');

        return $command === null ? 0 : 2;
    }
}
```

- [ ] **Step 4: Run the test to confirm it passes**

Run: `vendor/bin/pest tests/Cli/ApplicationTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Write the bin/femus entry point**

`bin/femus`:
```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

$autoload = null;
foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../../autoload.php'] as $candidate) {
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}

if ($autoload === null) {
    fwrite(STDERR, "Cannot locate composer autoloader. Run composer install.\n");
    exit(1);
}

require $autoload;

$app = new Femus\Cli\Application(dirname(__DIR__));

exit($app->run($argv, static function (string $line): void {
    fwrite(STDOUT, $line . PHP_EOL);
}));
```

- [ ] **Step 6: Make it executable and register the binary**

Run:
```bash
chmod +x bin/femus
```

In `composer.json`, add a top-level `"bin"` key (after `"autoload-dev"`):
```json
    "bin": ["bin/femus"],
```

- [ ] **Step 7: Smoke-test the entry point**

Run: `php bin/femus`
Expected: prints the usage lines, exit 0.

Run: `php bin/femus firmware:flash`
Expected: prints `usage: femus firmware:flash ...` (no target), exit code 2.

- [ ] **Step 8: Commit**

```bash
git add src/Cli/Application.php bin/femus composer.json tests/Cli/ApplicationTest.php
git commit -m "feat: femus CLI entry point with firmware:flash dispatch"
```

---

### Task 7: Documentation for firmware:flash

Document the new command in the firmware README and the root README user path.

**Files:**
- Modify: `firmware/README.md`
- Modify: `README.md`

**Interfaces:** none (docs only).

- [ ] **Step 1: Add a firmware:flash section to firmware/README.md**

Append to `firmware/README.md`:
```markdown
## Заливка прошивки: `femus firmware:flash`

Без Arduino IDE. Нужен только [arduino-cli](https://arduino.github.io/arduino-cli/latest/installation/)
(`brew install arduino-cli`) — femus сам поставит ядро `arduino:avr` и библиотеки.

```bash
# телефонный узел (мост BLE⇄радио)
vendor/bin/femus firmware:flash radio-bridge

# станция (ConfigurableFirmata + Radio + HX711)
vendor/bin/femus firmware:flash femus
```

Флаги:
- `--port=/dev/cu.usbserial-XXXX` — явный порт (по умолчанию `auto`, ищется сам).
- `--fqbn=arduino:avr:nano` — для Nano с новым бутлоадером (по умолчанию
  `arduino:avr:nano:cpu=atmega328old` — плата с Old Bootloader).
```

- [ ] **Step 2: Mention firmware:flash in the root README user path**

In `README.md`, add near the getting-started/usage area:
```markdown
### Прошивка платы одной командой

```bash
composer require sanchescom/femus
vendor/bin/femus firmware:flash radio-bridge   # или femus
```

Ставится только `arduino-cli`; femus подтянет ядро и библиотеки и зальёт готовый
скетч. Ни IDE, ни ручного управления библиотеками, ни C++.
```

- [ ] **Step 3: Run the full test suite**

Run: `vendor/bin/pest`
Expected: all tests green (existing + new CLI tests).

- [ ] **Step 4: Commit**

```bash
git add firmware/README.md README.md
git commit -m "docs: firmware:flash в firmware/README и корневом README"
```

---

## Self-Review

**Spec coverage:**
- `firmware:flash` backend = arduino-cli → Tasks 3, 5. ✓
- Local compile → `compileAndUpload` (Task 3). ✓
- femus installs core+libs → Task 5 loop. ✓
- CLI entry point `bin/femus`, no console framework → Task 6. ✓
- targets femus/radio-bridge, `--port=auto` via SerialPortLocator, `--fqbn` default old bootloader → Tasks 4, 5. ✓
- Testability via CommandRunner fake → Tasks 2–5. ✓
- Configurable bridge EEPROM (magic/node/peer), defaults 2/1, `/addr` `/peer` `/show`, ack, EEPROM.update → Task 1. ✓
- Docs: radio-ble-bridge.md (Task 1), firmware/README + root README (Task 7). ✓
- Out of scope (CI, station address, GitHub Releases) — not planned. ✓

**Placeholder scan:** No TBD/TODO; all code is concrete. ✓

**Type consistency:** `CommandRunner::run(array): CommandResult`, `ArduinoCli::compileAndUpload($sketchDir,$fqbn,$port)`, `FlashFirmware::run(?string,array,callable): int`, `FlashOptions::parse(array): self` used consistently across tasks. Target dir/lib maps identical in spec and Task 5. FQBN default string identical everywhere. ✓
