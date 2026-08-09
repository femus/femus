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

## Setup (recommended: xcodegen)

The Xcode project is generated from `project.yml` (Bluetooth permission and
settings included) — no manual project creation needed:

```bash
brew install xcodegen
cd ios/FemusRadioTerminal
xcodegen generate
open FemusRadioTerminal.xcodeproj
```

Then in Xcode:

1. Connect your iPhone with a USB cable and select it as the run destination.
2. Under **Signing & Capabilities**, choose your personal Apple ID as the
   **Team** (free personal-team signing — the app is valid for 7 days, then
   rebuild). If the bundle id is taken, change `PRODUCT_BUNDLE_IDENTIFIER` in
   `project.yml` and re-run `xcodegen generate`.
3. First run on a fresh device: enable **Developer Mode** on the iPhone
   (Settings → Privacy & Security → Developer Mode), then trust the certificate
   under Settings → General → VPN & Device Management. (Trusting the cert needs
   the internet once; after that the whole messenger runs fully offline.)
4. Press **Run** (⌘R).

<details>
<summary>Manual setup (without xcodegen)</summary>

1. Open Xcode and choose **File → New → Project…**
2. Select **iOS → App** with **Interface: SwiftUI**, **Language: Swift**.
3. Delete the generated `ContentView.swift`, then drag the three `.swift` files
   from this directory into the project (target: FemusRadioTerminal).
4. Add `NSBluetoothAlwaysUsageDescription` to Info with value
   `Connects to the femus radio node over Bluetooth`.
5. Select your iPhone, set the signing Team to your Apple ID, and Run.
</details>
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
