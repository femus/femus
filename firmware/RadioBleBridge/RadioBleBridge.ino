/*
 * femus RadioBleBridge — phone node of the radio messenger.
 * iPhone (BLE, HM-10) <-> 433 MHz ASK radio (FS1000A + MX-RM-5V).
 * No Firmata here: this node is a dumb line-oriented bridge.
 *
 * Node/peer addresses are configured over BLE and kept in EEPROM:
 *   /addr N   set this node's address (0-255)
 *   /peer N   set the peer's address (0-255)
 *   /show     print current addresses
 * Slash-lines are handled locally and never sent over the radio.
 */

#include <RH_ASK.h>
#include <SoftwareSerial.h>
#include <EEPROM.h>

#define EEPROM_MAGIC 0xF3
#define ADDR_MAGIC   0
#define ADDR_NODE    1
#define ADDR_PEER    2
#define DEFAULT_NODE 2
#define DEFAULT_PEER 1

// Radio pins — change these to match your wiring, then re-flash.
// (The RadioHead ASK library needs them fixed at compile time.)
#define RADIO_RX_PIN 11        // MX-RM-5V receiver DATA
#define RADIO_TX_PIN 12        // FS1000A transmitter DATA

SoftwareSerial ble(7, 8);      // D7 <- HM-10 TXD, D8 -> HM-10 RXD (via 1k/2k divider)
RH_ASK radio(2000, RADIO_RX_PIN, RADIO_TX_PIN);

byte nodeAddr;
byte peerAddr;

char lineBuf[51];
byte lineLen = 0;

void loadConfig()
{
  if (EEPROM.read(ADDR_MAGIC) != EEPROM_MAGIC) {
    EEPROM.update(ADDR_NODE, DEFAULT_NODE);
    EEPROM.update(ADDR_PEER, DEFAULT_PEER);
    EEPROM.update(ADDR_MAGIC, EEPROM_MAGIC);
  }
  nodeAddr = EEPROM.read(ADDR_NODE);
  peerAddr = EEPROM.read(ADDR_PEER);
}

void handleCommand()
{
  // lineBuf is NUL-terminated by the caller.
  if (strncmp(lineBuf, "/addr ", 6) == 0) {
    nodeAddr = (byte) atoi(lineBuf + 6);
    EEPROM.update(ADDR_NODE, nodeAddr);
    radio.setThisAddress(nodeAddr);
    ble.print("ok addr=");
    ble.println(nodeAddr);
  } else if (strncmp(lineBuf, "/peer ", 6) == 0) {
    peerAddr = (byte) atoi(lineBuf + 6);
    EEPROM.update(ADDR_PEER, peerAddr);
    ble.print("ok peer=");
    ble.println(peerAddr);
  } else if (strcmp(lineBuf, "/show") == 0) {
    ble.print("addr=");
    ble.print(nodeAddr);
    ble.print(" peer=");
    ble.println(peerAddr);
  } else {
    ble.println("err unknown");
  }
}

void setup()
{
  ble.begin(9600);
  radio.init();
  loadConfig();
  radio.setThisAddress(nodeAddr);
}

void loop()
{
  while (ble.available()) {
    char c = ble.read();
    if (c == '\n' || c == '\r') {
      if (lineLen > 0) {
        lineBuf[lineLen] = '\0';
        if (lineBuf[0] == '/') {
          handleCommand();
        } else {
          radio.setHeaderFrom(nodeAddr);
          radio.setHeaderTo(peerAddr);
          radio.send((uint8_t *) lineBuf, lineLen);
          radio.waitPacketSent();
        }
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
