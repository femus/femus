# Installation

## 1. Install the package

::: warning Pre-release
femus is not on Packagist yet. Until the first tagged release, install straight
from the GitHub repository.
:::

Add the repository to your project's `composer.json` and require the package:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/femus/femus" }
    ]
}
```

```bash
composer require femus/femus:dev-main
```

## 2. Install arduino-cli

femus flashes and talks to the board itself, but uploading firmware relies on
[arduino-cli](https://arduino.github.io/arduino-cli/latest/installation/):

::: code-group

```bash [macOS]
brew install arduino-cli
```

```bash [Linux]
curl -fsSL https://raw.githubusercontent.com/arduino/arduino-cli/master/install.sh | sh
```

:::

## 3. Flash the firmware

Connect your Arduino over USB and run:

```bash
vendor/bin/femus firmware:flash femus
```

This uploads the prebuilt `FemusFirmata` hex that ships inside the package — no
compilation, no libraries, no Arduino IDE. The board is autodetected; on the first run
arduino-cli will install the `arduino:avr` core (one-time, needed for the uploader).

Useful flags:

| Flag | Meaning |
|---|---|
| `--port=/dev/cu.usbserial-XXXX` | explicit serial port instead of autodetection |
| `--fqbn=arduino:avr:nano` | board type — default is `arduino:avr:nano:cpu=atmega328old` (the common clone with the old bootloader) |
| `--build` | compile from source instead of using the bundled hex |

::: tip Which bootloader do I have?
If flashing fails with `programmer is not responding`, your Nano likely has the other
bootloader — just try the other `--fqbn`. The firmware itself is identical; only the
upload speed differs.
:::

## 4. Check the connection

```bash
php vendor/femus/femus/examples/blink.php
```

The onboard LED should start blinking. That's it — you're driving hardware from PHP.
Continue to the [Quick Start](/guide/quick-start).
