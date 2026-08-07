# Board & Ports

## Connecting

```php
use Femus\Board;

$board = Board::firmata();                          // autodetect the port
$board = Board::firmata('/dev/cu.usbserial-1420');  // or be explicit
```

With no arguments, femus probes the serial ports that look like boards
(`/dev/cu.usbserial*`, `/dev/ttyUSB*`, `/dev/ttyACM*`…) and connects to the first one
that answers as a Firmata device.

To list candidate ports yourself:

- **macOS** — `ls /dev/cu.*`
- **Linux** — `ls /dev/ttyUSB* /dev/ttyACM*`

## Devices come from the board

Every driver is created through the board object, which owns the pins and the loop:

```php
$led = $board->led(13);
$button = $board->button(2);
$sensor = $board->analogSensor(0);
$scale = $board->loadCell(doutPin: 3, sckPin: 2);
$lcd = $board->lcd1602();                    // I2C, address 0x27
$radio = $board->radioLink(address: 1);
```

Lower-level access is there when you need it:

```php
use Femus\Contracts\PinMode;

$pin = $board->digitalPin(7, PinMode::Output);
$pin->write(true);

$raw = $board->analogPin(0);
$raw->onChange(fn (float $v) => var_dump($v));   // normalized 0.0–1.0

$i2c = $board->i2c();
```

## Multiple boards, one process

Boards can share a single event loop:

```php
use Femus\Runtime\StreamSelectLoop;

$loop = new StreamSelectLoop();
$stationA = Board::firmata('/dev/cu.usbserial-A', loop: $loop);
$stationB = Board::firmata('/dev/cu.usbserial-B', loop: $loop);

$stationA->button(2)->onPress(fn () => $stationB->led(13)->on());

$loop->run();   // drives both
```

See `examples/two-boards.php` in the repository for the complete script.

## When things go wrong

- `BoardException: Arduino did not respond` — wrong port, wrong firmware, or the board
  needs a second after replug. Flash it with `vendor/bin/femus firmware:flash femus`.
- Port disappears mid-session — the USB link dropped; femus surfaces it as a transport
  error rather than hanging.
- Non-default pins (a dead pin, a conflict) — most drivers accept explicit pin numbers:
  `radioLink(1, rxPin: 3, txPin: 4)`.
