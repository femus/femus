# 433 MHz Radio Link (Station Node A)

Main femus board station: 433 MHz ASK radio link with addressed packets and CRC, using FS1000A (TX) and MX-RM-5V (RX) modules.
Communicates with Node B (RadioBleBridge) and other nodes in the same network.

---

## Wiring to Arduino Nano

### FS1000A Transmitter (433 MHz)

| FS1000A pin | Arduino Nano pin | Notes |
|-------------|------------------|-------|
| DATA | D12 (default txPin) | ASK modulation signal |
| VCC | 5V | Module draws ≈20 mA during TX |
| GND | GND | Common ground |
| ANT | - | Solder a **17.3 cm** wire for quarter-wave monopole antenna |

### MX-RM-5V Receiver (433 MHz)

| MX-RM-5V pin | Arduino Nano pin | Notes |
|--------------|------------------|-------|
| DATA | D11 (default rxPin) | ASK demodulated signal |
| VCC | 5V | Module draws ≈5 mA during RX |
| GND | GND | Common ground |
| ANT | - | Solder a **17.3 cm** wire for quarter-wave monopole antenna |

---

## Antenna

**Critical:** Without an antenna, range is < 1 meter and packet loss is severe.

Each module requires a **17.3 cm quarter-wave monopole antenna** soldered to the ANT pad:

```
    Copper wire, 17.3 cm
         ↑
         │
      ┌──┴──┐
      │ ANT │
      └─────┘
    (FS1000A or MX-RM-5V)
```

Typical range with antenna: **50–100 meters** line-of-sight, depending on obstacles and antenna orientation.

---

## Firmware

Flash the Arduino Nano with **FemusFirmata** (includes radio support):

```bash
arduino-cli compile --fqbn arduino:avr:nano:cpu=atmega328old firmware/FemusFirmata
arduino-cli upload  --fqbn arduino:avr:nano:cpu=atmega328old -p <port> firmware/FemusFirmata
```

See `firmware/README.md` for library setup and IDE instructions.

---

## Quick Start

### Two Stations on the Same Machine (Testing)

**Terminal 1 — Node 1:**
```bash
php examples/radio-chat.php /dev/cu.usbserial-A 1 2
```

**Terminal 2 — Node 2:**
```bash
php examples/radio-chat.php /dev/cu.usbserial-B 2 1
```

(Replace port names with your actual serial ports.)

Type a message in either terminal; it is received and displayed on the other.

### Two Stations on Different Machines

Each machine runs:
```bash
php examples/radio-chat.php <port> <my-address> <peer-address>
```

Example:
- **Machine A, Node 1:** `php examples/radio-chat.php /dev/cu.usbserial-A 1 2`
- **Machine B, Node 2:** `php examples/radio-chat.php /dev/cu.usbserial-B 2 1`

Messages are transmitted via 433 MHz radio (≈50–100 meters range with antenna).

### Broadcast Mode

To send to all nodes, use address `127` (broadcast):

```bash
php examples/radio-chat.php <port> 1 127
```

Node 1 will broadcast; all listening nodes receive (but cannot reply via broadcast).

---

## Addressing

| Address | Meaning |
|---------|---------|
| 0–126 | Unicast node address |
| 127 | Broadcast (all nodes receive) |

Each node has a unique address (1, 2, 3, …). Nodes can listen to all incoming messages (via `onMessage` callback) and send to specific addresses or broadcast.

---

## API Overview

```php
use Femus\Board;
use Femus\Contracts\RadioLink;
use Femus\Contracts\RadioMessage;

$board = Board::firmata('/dev/cu.usbserial-A');
$radio = $board->radioLink(1);  // Node address 1

// Listen to incoming messages
$radio->onMessage(function (RadioMessage $m) {
    echo "From node {$m->from}: {$m->message}\n";
});

// Send a message
$radio->send(2, 'Hello node 2');        // Unicast to node 2
$radio->send(RadioLink::BROADCAST, 'Hello everyone');  // Broadcast

$board->run();
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| **Range < 1 meter, many lost packets** | Check antenna: must be 17.3 cm copper wire soldered to ANT pad on **both** modules |
| **No reception on peer node** | Verify FS1000A DATA is on D12; MX-RM-5V DATA is on D11; both have 5V and GND |
| **TX/RX swapped (messages only go one way)** | Swap D11 and D12 wires; D11 = RX, D12 = TX |
| **D11/D12 conflicts (LCD example plugged in)** | Unplug LCD wires; D11 and D12 are shared with radio module |
| **Random garbled/lost messages** | Check power supply (clean 5V, ≥1A); use USB powerbank if needed; separate GND rail for radio |
| **Firmware upload fails** | Ensure FemusFirmata libraries are installed: ConfigurableFirmata, HX711, RadioHead. See firmware/README.md |

---

## Protocol Details

Maximum message length: **50 bytes** (per RadioHead specification).

Messages include:
- **From address** (sender node ID)
- **To address** (destination node ID or broadcast)
- **CRC** (automatic, verified on reception)
- **Payload** (your text message)

Unaddressed messages and CRC failures are discarded by the firmware.

---

## Pin Conflict: D11/D12

**Warning:** Pins D11 and D12 are used by both radio and the parallel LCD1602 example
(`examples/lcd-clock-parallel.php`). To use radio:

1. Unplug all wires from D11 and D12 (if LCD is connected)
2. Connect FS1000A DATA to D12 and MX-RM-5V DATA to D11
3. Run radio examples
4. To use LCD again, disconnect radio wires and reconnect LCD wires

---

## Integration with RadioBleBridge (Node B)

Node B (see `docs/devices/radio-ble-bridge.md`) listens on address 2 by default and bridges
iPhone BLE messages to 433 MHz radio.

Typical setup:
- **Node A** (this station, address 1): `php examples/radio-chat.php <port> 1 2`
- **Node B** (RadioBleBridge on another Nano): hardcoded to address 2, sends all BLE input to Node 1

To test:
1. Flash Node B Arduino with RadioBleBridge sketch
2. Wire BLE + radio modules to Node B (see radio-ble-bridge.md)
3. Connect Node A to USB
4. Run radio-chat.php on Node A
5. Connect iPhone to Node B's HM-10 BLE device
6. Type in iPhone BLE terminal → Node A receives
7. Type in Node A terminal → iPhone BLE terminal receives (echoed via radio)

---

## Related Documents

- **FemusFirmata:** `firmware/README.md` — radio sysex protocol and wiring details
- **RadioBleBridge:** `docs/devices/radio-ble-bridge.md` — Node B (BLE + radio bridge)
- **Hardware Runs:** `docs/hardware-runs.md` — testing checklist and verification logs
