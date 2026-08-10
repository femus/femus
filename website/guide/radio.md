# 433 MHz Radio

femus turns the cheapest radio hardware there is — the FS1000A transmitter and
MX-RM-5V receiver pair, sold for pocket change — into an addressed packet link
between two boards, with CRC and sender/recipient headers
(RadioHead ASK under the hood in the firmware).

## Hardware

Each node needs both modules (femus radio is bidirectional):

| Module | Pin | Arduino |
|---|---|---|
| MX-RM-5V (receiver) | DATA | **D11** (default `rxPin`) |
| FS1000A (transmitter) | DATA | **D12** (default `txPin`) |
| both | VCC / GND | 5V / GND |

Solder a 17.3 cm wire into each module's `ANT` hole — quarter-wave for 433 MHz.

::: warning Antennas are not optional
Without antennas, 433 MHz barely reaches a few **centimetres**, and delivery is flaky
even when the boards touch — packets drop, one direction works while the other doesn't.
This looks exactly like broken hardware but isn't. The single 17.3 cm wire in each
module turns unreliable-at-2cm into solid-across-a-room. If radio "doesn't work,"
check antennas first.
:::

## API

```php
$radio = $board->radioLink(address: 1);   // this node's address, 0–255

$radio->onMessage(function ($m) {
    printf("[node %d] %s\n", $m->from, $m->message);
});

$radio->send(2, 'hello node two');        // to a specific node
$radio->send(RadioLink::BROADCAST, 'hi'); // or to everyone

$board->run();
```

Non-default pins are a constructor argument away — handy when a pin is occupied
(or, from lived experience, dead):

```php
$radio = $board->radioLink(1, rxPin: 3, txPin: 4);
```

## Chat between two machines

`examples/radio-chat.php` is a complete terminal chat:

```bash
# machine A                                   # machine B
php examples/radio-chat.php '' 1 2            php examples/radio-chat.php '' 2 1
```

Type a line, press Enter — it appears on the other machine's screen, over the air,
with no network between them.

## The radio messenger

The same stack powers the femus flagship demo: **iPhone ⇄ Mac text chat with no
internet and no cellular.** A second Arduino runs the bundled `RadioBleBridge`
firmware (BLE ⇄ radio, addresses configurable from the phone, stored in EEPROM), and
the [SwiftUI terminal app](https://github.com/femus/femus/tree/main/ios/FemusRadioTerminal)
connects to it over Bluetooth. Messages travel: iPhone → BLE → radio → your PHP process.

The first real message sent over it, from a phone in airplane mode with every network
off, was — fittingly — *"Am I online?"* (No. And that's the whole point.)

## Half-duplex: take turns

RH_ASK is half-duplex — a node can't receive while it's transmitting. If both ends
blast packets at the same time they collide and *both* sides hear nothing, which again
looks like broken hardware. Have the nodes **take turns** (send, then listen), the way
`radio-chat.php` does. A one-way flood is a clean way to test each direction in isolation.

## Honest limitations

- ASK at 2000 bps: short text packets (up to 50 bytes), not file transfer.
- No encryption — treat the air as public. Don't send secrets.
- Delivery is best-effort (CRC drops corrupt packets; retries are on the roadmap).
