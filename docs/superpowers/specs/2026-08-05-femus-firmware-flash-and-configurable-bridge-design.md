# Дизайн: `femus firmware:flash` + конфигурируемый RadioBleBridge

Дата: 2026-08-05
Статус: утверждён владельцем

## Контекст

Две утверждённые идеи из `docs/superpowers/ideas.md` (раздел «Утверждено владельцем
2026-08-05»):

1. **`femus firmware:flash`** — заливка готовых прошивок без Arduino IDE. Путь
   пользователя сжимается до `composer require → femus firmware:flash → пишешь PHP`.
2. **Конфигурируемый RadioBleBridge** — адреса узлов задаются по BLE и хранятся в
   EEPROM, один hex подходит любому пользователю без перекомпиляции.

Фичи связаны (конфигурируемый мост делает hex универсальным), но реализуются
независимо: фича 2 — чисто прошивочная (C++), фича 1 — новый PHP CLI.

**Порядок реализации:** сначала фича 2 (делает мост универсальным), затем фича 1.

## Состояние проекта на входе

- Пакет — чистая библиотека (`composer.json`: только `pestphp/pest` в dev). Нет
  `bin/`, нет console-фреймворка.
- `arduino-cli 1.5.1` установлен на машине владельца; отдельного `avrdude` нет
  (arduino-cli несёт avrdude внутри).
- Плата владельца: Arduino Nano V3 (CH340) — **Old Bootloader**, FQBN
  `arduino:avr:nano:cpu=atmega328old` (см. `docs/hardware-inventory.md`).
- `src/Transport/SerialPortLocator.php` уже умеет находить порт платы.
- `firmware/RadioBleBridge/RadioBleBridge.ino` — линейный мост BLE⇄радио с
  хардкод-константами `NODE_ADDRESS 2` / `PEER_ADDRESS 1`.

---

## Фича 1 — `femus firmware:flash`

### Решения

- **Backend заливки:** `arduino-cli` (уже установлен, несёт avrdude, знает FQBN
  Nano). Пользователь ставит один бинарник вместо IDE+библиотек.
- **Источник hex:** локальная сборка через `arduino-cli compile` (никаких
  бинарников в git, всегда актуально).
- **Зависимости сборки:** femus ставит их сам (`core install` + `lib install`),
  чтобы сохранить обещание «ни IDE, ни библиотек».

### Точка входа

Держим минимализм — без symfony/console. Добавляем исполняемый `bin/femus`:
крошечный роутер `argv → команда`.

### Новый неймспейс `src/Cli/`

- `Application.php` — разбирает `argv`, диспатчит по имени команды, печатает
  ошибки и usage, возвращает код выхода.
- `Command/FlashFirmware.php` — логика команды `firmware:flash`.
- `Arduino/ArduinoCli.php` — обёртка над `arduino-cli`: `version`,
  `core install`, `lib install`, `compile --upload`.
- `Process/CommandRunner.php` — интерфейс запуска процесса
  (`run(array $argv): CommandResult` с exit-кодом и выводом).
- `Process/SystemCommandRunner.php` — реальная реализация (`proc_open`,
  стриминг вывода).

`FlashFirmware` и `ArduinoCli` принимают `CommandRunner` в конструкторе — в
тестах подставляется фейковый runner.

### Команда

```
femus firmware:flash <target> [--port=auto] [--fqbn=...]
```

- `<target>`:
  - `femus` → каталог скетча `firmware/FemusFirmata`
  - `radio-bridge` → каталог скетча `firmware/RadioBleBridge`
- `--port` (по умолчанию `auto`): `auto` → `SerialPortLocator`; иначе явный путь
  (напр. `/dev/tty.usbserial-XXXX`).
- `--fqbn` (по умолчанию `arduino:avr:nano:cpu=atmega328old`): оверрайд для плат
  с новым бутлоадером или другого железа.

### Манифест библиотек на таргет

В коде зашит список зависимостей на каждый таргет (выводится из `#include`
соответствующего `.ino`):

- `radio-bridge`: `RadioHead` (`SoftwareSerial`, `EEPROM` — встроенные).
- `femus`: определяется по `#include` в `firmware/FemusFirmata/FemusFirmata.ino`
  на этапе реализации (ConfigurableFirmata и т.д.).

### Шаги выполнения

1. Проверить `arduino-cli` в PATH → иначе понятная ошибка с подсказкой установки
   (brew/apt/scoop), ненулевой код выхода.
2. `arduino-cli core install arduino:avr` (идемпотентно).
3. `arduino-cli lib install <deps таргета>` (идемпотентно).
4. Резолв порта: `--port=auto` → `SerialPortLocator`; если плата не найдена —
   ошибка с подсказкой указать `--port`.
5. `arduino-cli compile --fqbn <fqbn> --upload -p <port> <sketchdir>` — сборка и
   заливка одной командой; вывод стримится пользователю.
6. Итог: успех/ошибка с корректным кодом возврата.

### Тестирование (Pest)

Фейковый `CommandRunner`, реальная заливка не выполняется. Проверяем:

- корректный argv `arduino-cli compile --upload` для каждого таргета/порта/fqbn;
- дефолтный FQBN и оверрайд `--fqbn`;
- резолв порта через `SerialPortLocator` при `--port=auto`;
- ветку «нет arduino-cli» → понятная ошибка, ненулевой код;
- маппинг неизвестного `<target>` → ошибка usage.

---

## Фича 2 — Конфигурируемый RadioBleBridge

Правится `firmware/RadioBleBridge/RadioBleBridge.ino`.

### Изменения

- Убрать `#define NODE_ADDRESS` / `#define PEER_ADDRESS`. Ввести глобальные
  `byte nodeAddr, peerAddr`, читаемые из EEPROM при старте.
- `#include <EEPROM.h>`.

### Формат EEPROM (3 байта)

| Адрес | Назначение          |
|-------|---------------------|
| 0     | MAGIC `0xF3` (маркер валидности) |
| 1     | nodeAddr            |
| 2     | peerAddr            |

### `loadConfig()` в `setup()`

Читаем байт 0. Если ≠ `0xF3` (чистый чип = `0xFF`) → записать дефолты
**node=2, peer=1** (текущие значения телефонного узла, чтобы незаконфигуренный
hex сразу работал с существующей станцией) и MAGIC. Затем загрузить `nodeAddr`,
`peerAddr` в глобалы и вызвать `radio.setThisAddress(nodeAddr)`.

### Перехват команд в `loop()`

Когда собрана полная строка и `lineBuf[0] == '/'` — обрабатываем как команду,
в радио НЕ отправляем:

- `/addr N` — parse `N` (0–255) → `nodeAddr=N`, `EEPROM.update`,
  `radio.setThisAddress(N)`, ack `ok addr=N`.
- `/peer N` — `peerAddr=N`, `EEPROM.update`, ack `ok peer=N`.
- `/show` — ack `addr=<node> peer=<peer>`.
- прочее `/…` — ack `err unknown`.

Ack отправляется обратно через `ble.println(...)` — виден в SwiftUI-терминале.
Используем `EEPROM.update` (не `write`), чтобы не изнашивать ячейки при повторной
записи того же значения.

### Тестирование

Прошивочных юнит-тестов нет (как и у всей firmware в проекте). Валидация — через
`arduino-cli compile` (в том числе в составе `firmware:flash`).

---

## Документация

- `docs/devices/radio-ble-bridge.md` — команды конфигурации + формат EEPROM.
- `firmware/README.md` — раздел про `firmware:flash`.
- корневой `README.md` — использование `firmware:flash` в пути пользователя.

## Вне скоупа

- Настройка CI (GitHub Actions) для сборки hex — не нужна при локальной сборке.
- Конфигурируемый адрес станции (FemusFirmata) — задаётся из PHP через
  RadioFeature, не относится к мосту.
- Раздача предсобранных hex через GitHub Releases — на будущее.
