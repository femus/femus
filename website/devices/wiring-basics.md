# Wiring Basics for femus Projects

## Breadboard Anatomy

A standard 830-point solderless breadboard has two distinct zones:

```
  ┌─────────────────────────────────────────────────────┐
  │ +  . . . . . . . . . . . . . . . . . . . . . . . .  │  ← power rail (red, +)
  │ −  . . . . . . . . . . . . . . . . . . . . . . . .  │  ← power rail (blue, −)
  ├──────────────────────────────────────────────────────┤
  │    a b c d e     f g h i j                           │
  │ 1  ● ● ● ● ●     ● ● ● ● ●                           │
  │ 2  ● ● ● ● ●     ● ● ● ● ●                           │
  │    ... (terminal strips) ...                         │
  │30  ● ● ● ● ●     ● ● ● ● ●                           │
  ├──────────────────────────────────────────────────────┤
  │ −  . . . . . . . . . . . . . . . . . . . . . . . .  │  ← power rail (blue, −)
  │ +  . . . . . . . . . . . . . . . . . . . . . . . .  │  ← power rail (red, +)
  └─────────────────────────────────────────────────────┘
```

**Power rails (horizontal):** All holes in a single rail strip are connected
together from end to end. The red (+) rail carries 5V (or 3.3V); the blue (−)
rail is GND. Connect Arduino 5V → red rail, Arduino GND → blue rail; then
draw power from the rail to any module.

**Terminal strips (vertical):** Each numbered row is split into two halves
by the center gap (DIP-IC notch). Columns a–e are connected; columns f–j are
connected; the two halves are **not** connected to each other. Plugging a
component leg into any hole in a row connects it to every other component in
the same half-row.

**The golden rule: same row = electrically connected** (within one half).

---

## Which femus Devices Need Resistors?

| Device | Resistor needed? | Why |
|--------|-----------------|-----|
| Bare LED (no module) | **Yes — 220 Ω in series** | Limits current; without it the LED draws >100 mA and burns out instantly |
| HC-05 RXD line | **Yes — 1 kΩ / 2 kΩ voltage divider** | Arduino TX outputs 5V; HC-05 RXD is 3.3V-only; divider steps it down to ~3.3V |
| Button (KY-004 module or bare tactile) | None | femus enables INPUT_PULLUP; the Arduino's internal ~50 kΩ pull-up is used |
| Analog sensor modules (water level, photoresistor, joystick) | None | Onboard resistors on the module PCB |
| LCD 1602 with I²C backpack (PCF8574) | None | Backpack includes SDA/SCL pull-ups |
| MPU-6050 (GY-521 module) | None | Module includes I²C pull-ups and decoupling capacitors |
| HX711 load cell amplifier | None | Module includes the instrumentation amplifier and reference components |

**Bare LED wiring:**

```
Arduino pin ──── 220 Ω ──── LED anode (+, longer leg)
                             LED cathode (−, shorter leg) ──── GND
```

Choose 220 Ω for a bright result at 5V. 330 Ω is also acceptable (slightly dimmer, cooler).

---

## Dupont Wire Types

| Type | Connector ends | Use for |
|------|---------------|---------|
| Male–Male (M-M) | Pin on both ends | Breadboard to breadboard, breadboard to Arduino pin headers |
| Male–Female (M-F) | Pin on one end, socket on the other | Arduino header pin → module header socket (e.g. HC-05, GY-521, HX711) |
| Female–Female (F-F) | Socket on both ends | Module socket → module socket (rarely needed in this kit) |

For all sensor modules in the femus kit, **M-F wires** run from the Arduino
header to the module header. For breadboard prototyping (voltage dividers,
bare LEDs), **M-M wires** connect rows to each other and to the Arduino.

---

## The Golden Rule: Common GND

Every device connected to the Arduino must share the **same GND** reference.
This is the single most common source of mysterious non-working circuits:

- Connect Arduino GND → breadboard blue (−) rail.
- Connect every module's GND pin to the same blue rail (or directly to
  another Arduino GND pin).
- When using an external power supply for motors or high-current devices,
  always bridge its GND to the Arduino GND.

Without a common GND, signals have no return path and the Arduino reads
floating or incorrect values even if all other wiring is correct.
