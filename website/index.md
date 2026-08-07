---
layout: home

hero:
  name: femus
  text: Hardware for PHP developers
  tagline: Buttons, sensors, scales, radio and GSM — driven from plain PHP. No C++, no Arduino IDE.
  actions:
    - theme: brand
      text: Quick Start
      link: /guide/quick-start
    - theme: alt
      text: View on GitHub
      link: https://github.com/femus/femus

features:
  - icon: 🐘
    title: Plain PHP, real hardware
    details: One Board object, typed device drivers and an event loop. The language you already know, talking to physical pins.
  - icon: 🔋
    title: Batteries included
    details: Firmware ships precompiled inside the package. femus firmware:flash finds your board and uploads it — the only extra tool is arduino-cli.
  - icon: 🧪
    title: Testable without hardware
    details: Every driver runs against FakeBoard. Unit-test your hardware logic in CI — no soldering required.
  - icon: 📡
    title: Not just blinking LEDs
    details: Addressed 433 MHz packet radio, load cells, GSM/SMS, I2C displays — enough to build a real device.
---
