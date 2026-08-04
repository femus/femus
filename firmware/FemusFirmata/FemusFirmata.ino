/*
 * FemusFirmata — ConfigurableFirmata bundle for the femus PHP framework:
 * digital I/O, analog inputs, I2C plus the femus HX711 load cell module.
 * Flash once; drive everything from PHP.
 */

#include <ConfigurableFirmata.h>
#include <DigitalInputFirmata.h>
#include <DigitalOutputFirmata.h>
#include <AnalogInputFirmata.h>
#include <I2CFirmata.h>
#include <FirmataExt.h>
#include <FirmataReporting.h>
#include "Hx711Feature.h"
#include "RadioFeature.h"

DigitalInputFirmata digitalInput;
DigitalOutputFirmata digitalOutput;
AnalogInputFirmata analogInput;
I2CFirmata i2c;
Hx711Feature hx711;
RadioFeature radio;
FirmataExt firmataExt;
FirmataReporting reporting;

void systemResetCallback()
{
  firmataExt.reset();
}

void setup()
{
  Firmata.setFirmwareNameAndVersion("FemusFirmata", FIRMATA_FIRMWARE_MAJOR_VERSION, FIRMATA_FIRMWARE_MINOR_VERSION);
  firmataExt.addFeature(digitalInput);
  firmataExt.addFeature(digitalOutput);
  firmataExt.addFeature(analogInput);
  firmataExt.addFeature(i2c);
  firmataExt.addFeature(hx711);
  firmataExt.addFeature(radio);
  firmataExt.addFeature(reporting);
  Firmata.attach(SYSTEM_RESET, systemResetCallback);
  Firmata.begin(57600);
}

void loop()
{
  while (Firmata.available()) {
    Firmata.processInput();
  }
  boolean elapsed = reporting.elapsed();
  if (elapsed) {
    analogInput.report(elapsed);
    i2c.report(elapsed);
  }
  hx711.report(elapsed);
  radio.report(elapsed);
}
