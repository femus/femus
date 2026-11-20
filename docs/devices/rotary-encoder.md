# KY-040 Rotary Encoder

An endless knob: turn it and count steps in either direction, press it — the
shaft is also a button. The KY-040 is the ubiquitous hobby module (volume knobs,
menu navigation). Plain digital I/O, so the standard firmware is enough.

## Wiring

| KY-040 pin | Arduino |
|------------|---------|
| CLK | D4 |
| DT  | D5 |
| SW  | D6 (optional — the push switch) |
| +   | 5V |
| GND | GND |

Any digital pins work; the driver enables internal pull-ups.

## Usage

```php
use Femus\Board;

$board = Board::firmata();

$encoder = $board->rotaryEncoder(clkPin: 4, dtPin: 5, swPin: 6);

$encoder->onTurn(function (int $direction, int $position) {
    // $direction is +1 (clockwise) or -1; $position is the running total
    echo "turned {$direction}, now at {$position}\n";
});

$encoder->button()?->onPress(fn () => print("pressed!\n"));

$board->run();
```

`position()` returns the net step count since start, `resetPosition()` zeroes
it. Omit `swPin` if you don't wire the switch — `button()` is then `null`.

See [examples/rotary-encoder.php](https://github.com/femus/femus/blob/main/examples/rotary-encoder.php)
for a volume-knob demo.

## How it decodes

The two pins produce a quadrature signal. The driver acts on the falling edge
of CLK and reads DT at that moment — one step per detent on a typical KY-040.
DT high at the edge counts as clockwise, low as counter-clockwise.

## Gotchas

- **Counts backwards?** Swap the CLK and DT wires (or pin numbers) — module
  batches differ.
- Cheap encoders bounce; if you see occasional double-steps on real hardware,
  that's the contacts, not your code. Slowing the turn helps; so does a small
  (~100 nF) capacitor from CLK to GND.
- The `+` pin only powers the module's onboard pull-ups; the encoder itself is
  passive.
