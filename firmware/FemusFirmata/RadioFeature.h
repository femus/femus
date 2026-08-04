#pragma once

#include <ConfigurableFirmata.h>
#include <FirmataFeature.h>
#include <RH_ASK.h>

#define FEMUS_RADIO_COMMAND 0x0D
#define FEMUS_RADIO_ATTACH 0x00
#define FEMUS_RADIO_SEND 0x01
#define FEMUS_RADIO_RECV 0x02
#define FEMUS_RADIO_BROADCAST 0x7F
#define FEMUS_RADIO_MAX_LEN 50

// 433 MHz ASK link (FS1000A + MX-RM-5V) with from/to headers and CRC via RadioHead.
// One radio per board: the protocol carries no channel id.
class RadioFeature : public FirmataFeature
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
    if (command != FEMUS_RADIO_COMMAND) {
      return false;
    }
    if (argc >= 4 && argv[0] == FEMUS_RADIO_ATTACH) {
      if (driver != NULL) {
        delete driver;
      }
      address = argv[1];
      driver = new RH_ASK(2000, argv[2], argv[3]);
      driver->init();
      driver->setThisAddress(address);
      attached = true;
    } else if (attached && argc >= 2 && argv[0] == FEMUS_RADIO_SEND) {
      byte to = argv[1] == FEMUS_RADIO_BROADCAST ? RH_BROADCAST_ADDRESS : argv[1];
      byte buf[FEMUS_RADIO_MAX_LEN];
      byte len = 0;
      for (byte i = 2; i + 1 < argc && len < sizeof(buf); i += 2) {
        buf[len++] = (argv[i] & 0x7F) | ((argv[i + 1] & 0x01) << 7);
      }
      driver->setHeaderFrom(address);
      driver->setHeaderTo(to);
      driver->send(buf, len);
      driver->waitPacketSent();
    }
    return true;
  }

  // Called every sketch loop iteration: RH_ASK reception must be polled often.
  void report(bool elapsed) override
  {
    (void) elapsed;
    if (!attached) {
      return;
    }
    uint8_t buf[RH_ASK_MAX_MESSAGE_LEN];
    uint8_t len = sizeof(buf);
    if (!driver->recv(buf, &len)) {
      return;
    }
    byte to = driver->headerTo() == RH_BROADCAST_ADDRESS ? FEMUS_RADIO_BROADCAST : driver->headerTo();
    Firmata.startSysex();
    Firmata.write(FEMUS_RADIO_COMMAND);
    Firmata.write(FEMUS_RADIO_RECV);
    Firmata.write(driver->headerFrom() & 0x7F);
    Firmata.write(to & 0x7F);
    for (uint8_t i = 0; i < len; i++) {
      Firmata.write(buf[i] & 0x7F);
      Firmata.write((buf[i] >> 7) & 0x01);
    }
    Firmata.endSysex();
  }

private:
  RH_ASK *driver = NULL;
  byte address = 0;
  bool attached = false;
};
