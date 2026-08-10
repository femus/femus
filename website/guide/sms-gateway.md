# SMS Gateway — your own internet over SMS

A personal box you keep at home: a modem with a SIM, driven by femus. Text it from
**any phone with no data** — out of coverage, roaming, or on a dumb phone — and it
reaches the internet for you and texts the answer back. Your home line becomes your
family's backup internet.

::: warning Status: software prototype
The gateway logic below is built and unit-tested against fakes (no hardware, no
network). The live pieces — a real Claude-backed `AiClient` and the LTE modem — are
wired in when the hardware arrives. See the [roadmap note](#whats-built-vs-next).
:::

## The idea

```
Your phone (SMS only) → cell network → SMS
   → home modem → femus SmsGateway (the box has internet)
   → AI / email / lookup → concise answer → SMS back to your phone
```

It's an **agent**, not raw internet: the box understands your request, does the work,
and returns a digested answer in one or two SMS. That's the whole trick — you get the
*information*, not megabytes of *data*. (Tunnelling real data over SMS is possible too;
see [Packet mode](#packet-mode-femus-femus).)

## How it's built

Everything sits on the existing [`GsmModem`](/guide/gsm) and is fully decoupled for
testing:

| Piece | Role |
|---|---|
| `GsmModem` | raw SMS in/out (already in femus) |
| `SmsGateway` | auth (whitelist) + routing (`/command` vs. plain question) |
| `AiClient` | interface — answers a question (Claude in production, fake in tests) |
| `SmsCommand` | a slash-command, e.g. `PingCommand`, `HelpCommand` |
| `SmsTransport` / `SmsReassembler` | split/rejoin long payloads (packet mode) |

```php
use Femus\Gsm\Gateway\SmsGateway;
use Femus\Gsm\Gateway\ModemSender;
use Femus\Gsm\Gateway\Command\PingCommand;

$gateway = new SmsGateway(
    new ModemSender($modem),
    $ai,                                   // your AiClient
    commands: [new PingCommand()],
    allowedNumbers: ['+15551234567'],      // your/family numbers; empty = open
);

$modem->onSmsReceived(fn ($sms) => $gateway->handle($sms));
$modem->run();
```

Text `/ping` → `pong`. Text `weather in Halifax?` → the AI agent answers.
See `examples/sms-gateway.php`.

## Whitelist first

A personal box should only answer **you** (and family). Pass `allowedNumbers` — anyone
else is ignored. Leaving it empty serves everyone, which is fine for a bench test but
not for a box on a live SIM. Keeping it personal also sidesteps carrier bulk-SMS
(A2P) rules — you're just texting your own device.

## Packet mode (femus↔femus)

Short answers and long text both reach any phone (the network concatenates long SMS).
But to move **raw data** — a file, a structured blob — `SmsTransport` chunks it into
`[id:seq/total]` segments that a peer running `SmsReassembler` stitches back together.

This only works **femus-to-femus** (a normal phone can't rejoin the packets), and it's
slow — ~140 bytes per SMS, seconds each. Use it for tiny blobs between two femus boxes
where only SMS gets through, not for browsing.

## Honest limits

- **~150 chars per SMS.** Answers are digested to fit; long ones split into parts.
- **Latency** is seconds to a minute per round trip — fine for a backup, not a chat.
- **Roaming SMS** abroad may cost a little, but far less than roaming data — text your
  home box instead of paying for a data plan.
- The box must be **always on** with home internet — that's the femus
  [autonomous node](https://github.com/femus/femus/tree/main/deploy) (systemd) you
  already have.

## What's built vs. next

**Built & tested now** (against fakes, no hardware): `SmsGateway`, `SmsTransport`,
`SmsReassembler`, `SmsCommand` + `PingCommand`/`HelpCommand`, `ModemSender`.

**Next** (needs hardware / credentials): a real Claude-backed `AiClient`
(API key + the box's internet), a `MailService` (IMAP/SMTP), and wiring to the
LTE modem. The gateway is ready for them — they just implement the interfaces.
