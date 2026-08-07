# Testing Without Hardware

The femus habit that pays off most: **your hardware logic is just PHP, so test it like
PHP.** Every driver is built against interfaces, and `FakeBoard` implements them all
with simulation helpers. No board on your desk, no board in your CI — the tests still
mean something.

## The idea

`FakeBoard` is a drop-in replacement for a Firmata board:

```php
use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

$board = new FakeBoard(new StreamSelectLoop());

$led = $board->led(13);
$board->button(2)->onPress(fn () => $led->on());

$board->simulateInput(2, false);              // "press" the button (active-low)
expect($board->pin(13)->read())->toBeTrue();  // the LED turned on
```

Write your application code against `BoardInterface` and it will never know
the difference:

```php
final readonly class DoorAlarm
{
    public function __construct(BoardInterface $board)
    {
        $board->motionSensor(4)->onMotion(
            fn () => $board->buzzer(8)->beep(0.2)
        );
    }
}
```

In production you pass `Board::firmata()`, in tests — a `FakeBoard`.

## Simulation helpers

| Helper | Simulates |
|---|---|
| `simulateInput(pin, high)` | a digital edge (button press, motion) |
| `simulateAnalog(channel, raw)` | an analog reading |
| `simulateScaleReading(dout, sck, raw)` | an HX711 load-cell sample |
| `simulateRadioMessage(addr, from, to, msg)` | an incoming radio packet |
| `scheduleInput(delay, pin, high)` | the same, but after loop time passes |

The `schedule*` variants fire through the event loop, so debounce windows, timers and
ordering behave exactly as they would with real signals.

## Inspection helpers

- `$board->pin(13)` — the fake digital pin: `read()` its last written state.
- `$board->fakeAnalogPin(0)`, `$board->fakeScale(3, 2)`, `$board->fakeRadio(1)`,
  `$board->fakeI2c()` — typed access to what your code did to the hardware:
  bytes written to I2C, packets sent over radio, and so on.

## A real example

This is how femus tests its own button debouncing — three edges inside the debounce
window collapse into one press:

```php
it('button: bounce within the debounce window is suppressed', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $button = $board->button(2, debounceSeconds: 0.05);
    $presses = 0;
    $button->onPress(function () use (&$presses) { $presses++; });

    $board->simulateInput(2, false); // press
    $board->simulateInput(2, true);  // bounce — instant release
    $board->simulateInput(2, false); // bounce — instant re-press

    expect($presses)->toBe(1);
});
```

The entire femus suite — 150+ Pest tests covering drivers, the Firmata protocol,
radio framing and the CLI — runs exactly this way on every push. Your project can too.
