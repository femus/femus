/*
 * femus RadioBleBridge — phone node of the radio messenger.
 * iPhone (BLE, HM-10) <-> 433 MHz ASK radio (FS1000A + MX-RM-5V).
 * No Firmata here: this node is a dumb line-oriented bridge.
 */

#include <RH_ASK.h>
#include <SoftwareSerial.h>

#define NODE_ADDRESS 2
#define PEER_ADDRESS 1

SoftwareSerial ble(7, 8);      // D7 <- HM-10 TXD, D8 -> HM-10 RXD (via 1k/2k divider)
RH_ASK radio(2000, 11, 12);    // rxPin D11 (MX-RM-5V DATA), txPin D12 (FS1000A DATA)

char lineBuf[51];
byte lineLen = 0;

void setup()
{
  ble.begin(9600);
  radio.init();
  radio.setThisAddress(NODE_ADDRESS);
}

void loop()
{
  while (ble.available()) {
    char c = ble.read();
    if (c == '\n' || c == '\r') {
      if (lineLen > 0) {
        radio.setHeaderFrom(NODE_ADDRESS);
        radio.setHeaderTo(PEER_ADDRESS);
        radio.send((uint8_t *) lineBuf, lineLen);
        radio.waitPacketSent();
        lineLen = 0;
      }
    } else if (lineLen < sizeof(lineBuf) - 1) {
      lineBuf[lineLen++] = c;
    }
  }

  uint8_t buf[RH_ASK_MAX_MESSAGE_LEN];
  uint8_t len = sizeof(buf);
  if (radio.recv(buf, &len)) {
    ble.write(buf, len);
    ble.println();
  }
}
