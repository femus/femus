#pragma once

#include <ConfigurableFirmata.h>
#include <FirmataFeature.h>
#include <HX711.h>

#define FEMUS_HX711_COMMAND 0x0E
#define FEMUS_HX711_ATTACH 0x00
#define FEMUS_HX711_READING 0x01
#define FEMUS_HX711_INTERVAL_MS 100

// Streams raw HX711 readings as femus sysex frames (one HX711 per board).
class Hx711Feature : public FirmataFeature
{
public:
  boolean handlePinMode(byte pin, int mode) override
  {
    return false;
  }

  void handleCapability(byte pin) override
  {
  }

  void reset() override
  {
    attached = false;
  }

  boolean handleSysex(byte command, byte argc, byte *argv) override
  {
    if (command != FEMUS_HX711_COMMAND) {
      return false;
    }
    if (argc >= 3 && argv[0] == FEMUS_HX711_ATTACH) {
      scale.begin(argv[1], argv[2]);
      attached = true;
      lastReportAt = 0;
    }
    return true;
  }

  // Called from FirmataExt dispatch; rate-limited internally (ignores elapsed).
  void report(bool elapsed) override
  {
    if (!attached || millis() - lastReportAt < FEMUS_HX711_INTERVAL_MS) {
      return;
    }
    if (!scale.is_ready()) {
      return;
    }
    lastReportAt = millis();
    // read() returns float (raw 24-bit signed ADC value); preserve sign via int32_t.
    int32_t raw = (int32_t) scale.read();
    uint32_t value = (uint32_t) raw;
    Firmata.startSysex();
    Firmata.write(FEMUS_HX711_COMMAND);
    Firmata.write(FEMUS_HX711_READING);
    for (byte i = 0; i < 5; i++) {
      Firmata.write((value >> (7 * i)) & 0x7F);
    }
    Firmata.endSysex();
  }

private:
  HX711 scale;
  bool attached = false;
  unsigned long lastReportAt = 0;
};
