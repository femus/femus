# FemusRadioTerminal — iOS BLE Terminal

A minimal SwiftUI app that connects to an HM-10 BLE-UART module wired to an
Arduino running the [RadioBleBridge](../../firmware/RadioBleBridge/RadioBleBridge.ino)
sketch. It lets you send and receive radio messages from your iPhone.

## Files

| File | Purpose |
|------|---------|
| `BluetoothTerminal.swift` | Core BLE logic — scan, connect, send/receive |
| `ContentView.swift` | SwiftUI chat UI |
| `FemusRadioTerminalApp.swift` | `@main` app entry point |

## Setup in Xcode

1. Open Xcode and choose **File → New → Project…**
2. Select **iOS → App**. Fill in:
   - **Product Name**: `FemusRadioTerminal`
   - **Interface**: SwiftUI
   - **Language**: Swift
3. Click **Next**, choose a location, and **Create**.
4. In the project navigator, **delete** the generated `ContentView.swift`
   (move to Trash when prompted).
5. Drag the three `.swift` files from this directory into the Xcode project
   navigator (under the `FemusRadioTerminal` group). Make sure
   **Add to targets: FemusRadioTerminal** is checked.
6. Open `Info.plist` (or the target's **Info** tab) and add the key:
   - **Key**: `NSBluetoothAlwaysUsageDescription`
   - **Value**: `Connects to the femus radio node over Bluetooth`
7. Connect your iPhone with a USB cable.
8. Select your iPhone as the run destination in the toolbar.
9. Under **Signing & Capabilities**, select your personal Apple ID as the
   team (free personal-team signing — the app will be valid for 7 days,
   after which you need to rebuild and re-install).
10. Press **Run** (⌘R).

> **The iOS Simulator does not support Bluetooth — a real device is required.**

## Syntax check result

Verified clean with:

```
xcrun swiftc -parse ios/FemusRadioTerminal/*.swift \
  -sdk $(xcrun --sdk iphoneos --show-sdk-path) \
  -target arm64-apple-ios16.0
```

## Testing flow

1. Power the RadioBleBridge node (see
   [docs/devices/radio-ble-bridge.md](../../docs/devices/radio-ble-bridge.md)).
2. Launch the app on your iPhone. It automatically scans for and connects to
   the first BLE device advertising service `FFE0` (the HM-10 module).
   The status bar turns green and shows **Connected to \<name\>**.
3. On your workstation, start the PHP radio chat station:
   ```bash
   php examples/radio-chat.php /dev/ttyUSB0
   ```
4. Type a message in the app (max 50 characters — enforced by the UI) and
   tap **Send**. The message appears in the PHP terminal.
5. When the PHP station sends a reply, it flows back to the app and appears
   as an incoming (gray) bubble.

## Protocol notes

- **Service UUID**: `FFE0` — **Characteristic UUID**: `FFE1` (notify + write without response)
- Outgoing: text + `\n`, chunked into ≤ 20-byte BLE 4.0 packets
- Incoming: bytes accumulated in a buffer, split on `\n`, `\r` trimmed
- Maximum message length: 50 characters (radio node constraint)
- Tap the status bar at any time to disconnect and rescan
