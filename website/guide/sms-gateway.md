# SMS Gateway — your own internet over SMS

A personal box you keep at home: a modem with a SIM, driven by femus. Text it from
**any phone with no data** — out of coverage, roaming, or on a dumb phone — and it
reaches the internet for you and texts the answer back. Your home line becomes your
family's backup internet.

::: tip Status: running on real hardware
Verified end to end on 2026-09-21 with a SIMCOM A7670G (LTE Cat-1): a question texted
from a phone with no data came back as an answer from a live AI, and `/weather` replied
with a real forecast. Wiring and gotchas are in
[docs/hardware-runs.md](https://github.com/femus/femus/blob/main/docs/hardware-runs.md)
and the [modem page](/devices/gsm-modem).
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

### Commands that know today

An AI answers from memory, so it cannot tell you whether it is raining right now —
and that is exactly what you text from a trailhead with one bar of signal and no data.
`WeatherCommand` fetches the real forecast from Open-Meteo, which needs no API key and
no account, so the box keeps working years later with nothing to renew:

```php
use Femus\Gsm\Gateway\Command\WeatherCommand;

commands: [new PingCommand(), new WeatherCommand()],
```

```
/weather Halifax          → Halifax: 13°C, overcast, wind 15.6 km/h. 6h: 13°. 80% precip at 05:00.
/weather 46.81 -71.21     → 46.81,-71.21: 10°C, overcast, wind 4.3 km/h. 6h: 10°.
```

Place names are geocoded; two numbers are taken as coordinates, which is what a phone's
GPS gives you when nothing around has a name. The reply is trimmed to what changes a
decision — conditions now, the temperature in six hours, the worst hour for precipitation,
and a shout if a thunderstorm is coming — because it has to fit in an SMS.

Write your own the same way: implement `SmsCommand`, take an `HttpClient` in the
constructor, and the command is unit-testable against canned JSON with no network.

## The AI agent (Claude over SMS)

The default reply path is an AI agent: a plain-text question goes to Claude, which
answers concisely enough to fit an SMS. `ClaudeAiClient` talks to the Claude Messages
API over raw HTTP (femus stays dependency-free — no SDK pulled in); set
`ANTHROPIC_API_KEY` and it works:

```php
use Femus\Gsm\Gateway\ClaudeAiClient;

$ai = new ClaudeAiClient(getenv('ANTHROPIC_API_KEY')); // default model: claude-opus-4-8
```

The HTTP transport is injectable (`HttpClient`), so the client is unit-tested against a
fake with no network. Prefer the official Anthropic SDK? Implement `AiClient` with it —
the gateway doesn't care which you use.

### Any other provider, including free ones

Most providers speak the OpenAI chat-completions format, so one client covers them all.
`OpenAiCompatibleAiClient` takes the key, the model and the endpoint — Gemini's free tier
is the default, and the box costs nothing to run:

```php
use Femus\Gsm\Gateway\OpenAiCompatibleAiClient;

$ai = new OpenAiCompatibleAiClient(getenv('GEMINI_API_KEY')); // gemini-3.6-flash

$ai = new OpenAiCompatibleAiClient(            // or Groq, OpenRouter, a local Ollama…
    getenv('GROQ_API_KEY'),
    model: 'llama-3.3-70b-versatile',
    endpoint: 'https://api.groq.com/openai/v1/chat/completions',
);
```

A Gemini key is free from [AI Studio](https://aistudio.google.com/apikey), no card needed.
Keep keys and phone numbers in the environment, never in the repository:

```bash
GEMINI_API_KEY=... SMS_ALLOWED=+15551234567 php examples/sms-gateway.php /dev/ttyUSB0
```

This is what makes "SMS internet" actually useful today: you don't tunnel raw data, you
get an intelligent, digested answer. It's txtWeb / Google SMS, but AI-powered and
self-hosted. Images and other media can't ride SMS — the plan is to transcode them to
text (a marker like `[photo]`, or a one-line Claude-vision description) rather than send
bytes.

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

**Working on hardware:** the whole path — modem in, whitelist, commands, AI answer,
SMS back — with `SmsGateway`, `ModemSender`, `PingCommand`/`HelpCommand`/`WeatherCommand`,
and `OpenAiCompatibleAiClient` against the live Gemini free tier. Non-Latin text (Cyrillic,
emoji) travels as UCS-2 in both directions, split across messages when it exceeds 70
characters.

**Built but only tested against fakes:** `SmsTransport` / `SmsReassembler` (packet mode),
`ClaudeAiClient` (the code path is identical to the verified Gemini one, but no live key
was on hand).

**Next:** a `MailService` (IMAP/SMTP) for mail over SMS, an optional Telegram relay
(MTProto user-bot), image→text via a vision model, and moving the box to a Raspberry Pi,
where the modem comes up as a USB network interface and gives the box its own internet.
