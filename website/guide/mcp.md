# AI Agents (MCP)

::: tip Optional
Everything on this page is an add-on. femus is fully usable without any AI —
the CLI, the docs and the examples assume nothing but a human and a terminal.
:::

femus ships an [MCP](https://modelcontextprotocol.io) server — an AI agent can
discover your boards, flash firmware and drive pins **on real hardware**. MCP is an
open standard: the server is plain JSON-RPC over stdio and works with any MCP
client — Claude Code, Cursor, VS Code, Windsurf, Cline, Zed, Gemini CLI and others.

::: code-group

```bash [Claude Code]
claude mcp add femus -- php vendor/bin/femus mcp
```

```json [Cursor / VS Code / generic]
{
    "mcpServers": {
        "femus": {
            "command": "php",
            "args": ["vendor/bin/femus", "mcp"]
        }
    }
}
```

:::

Then just ask:

> "Is there a board connected? Blink the LED on pin 13, then tell me what the
> sensor on A0 reads."

## Tools

| Tool | What it does |
|---|---|
| `scan_ports` | list serial ports that look like boards |
| `flash_firmware` | upload the bundled hex (`femus` or `radio-bridge` target) |
| `digital_write` | set a pin HIGH/LOW — LEDs, relays, buzzers |
| `digital_read` | read a pin (internal pull-up enabled) |
| `analog_read` | read A0…A7, normalized 0–1 |

Every tool takes an optional `port`; without it the board is autodetected.
Failures come back as readable text (`Cannot connect to the board: …`), so the
agent can reason about what went wrong and try the next step.

## Docs for LLMs

The whole documentation is also published in LLM-friendly form:

- [`llms.txt`](https://femus.github.io/femus/llms.txt) — table of contents
- [`llms-full.txt`](https://femus.github.io/femus/llms-full.txt) — every page in one file

Point any model at `llms-full.txt` and it knows femus.

## Why this matters

Debugging hardware is a conversation: *check the port, read the pin, flip it, read
again*. An agent with these tools does that loop autonomously — femus was built for
exactly this workflow (its own hardware was brought up this way).
