import CoreBluetooth
import Foundation

// MARK: - TerminalMessage

struct TerminalMessage: Identifiable {
    let id = UUID()
    let text: String
    let isOutgoing: Bool
}

// MARK: - BluetoothTerminal

final class BluetoothTerminal: NSObject, ObservableObject, CBCentralManagerDelegate, CBPeripheralDelegate {

    // MARK: Published State

    @Published var status: String = "Initializing…"
    @Published var isConnected: Bool = false
    @Published var messages: [TerminalMessage] = []

    // MARK: BLE Constants

    private let serviceUUID = CBUUID(string: "FFE0")
    private let characteristicUUID = CBUUID(string: "FFE1")
    private let maxWriteLength = 20          // BLE 4.0 ATT MTU limit
    private let newline: UInt8 = 0x0A        // '\n'
    private let carriageReturn: UInt8 = 0x0D // '\r'

    // MARK: Private State

    private var central: CBCentralManager!
    private var peripheral: CBPeripheral?
    private var txCharacteristic: CBCharacteristic?
    private var rxBuffer = Data()

    // MARK: Init

    override init() {
        super.init()
        central = CBCentralManager(delegate: self, queue: DispatchQueue.main)
    }

    // MARK: Public API

    /// Send text to the radio node. Appends "\n", chunks into ≤20-byte BLE writes.
    func send(_ text: String) {
        guard isConnected, let characteristic = txCharacteristic else { return }

        // Append the message to the UI immediately
        let trimmed = String(text.prefix(50))
        messages.append(TerminalMessage(text: trimmed, isOutgoing: true))

        // Encode with trailing newline
        let payload = (trimmed + "\n").data(using: .utf8) ?? Data()

        // Chunk into ≤20-byte writes without response
        var offset = 0
        while offset < payload.count {
            let end = min(offset + maxWriteLength, payload.count)
            let chunk = payload[offset..<end]
            peripheral?.writeValue(chunk, for: characteristic, type: .withoutResponse)
            offset = end
        }
    }

    /// Disconnect current peripheral and restart scanning.
    func rescan() {
        central.stopScan()
        if let p = peripheral {
            central.cancelPeripheralConnection(p)
            peripheral = nil
        }
        txCharacteristic = nil
        rxBuffer = Data()
        isConnected = false
        startScan()
    }

    // MARK: - Private Helpers

    private func startScan() {
        guard central.state == .poweredOn else { return }
        status = "Scanning…"
        central.scanForPeripherals(withServices: [serviceUUID], options: nil)
    }

    private func handleIncomingData(_ data: Data) {
        rxBuffer.append(data)

        // Split on 0x0A ('\n') and emit complete lines
        while let newlineIndex = rxBuffer.firstIndex(of: newline) {
            var line = rxBuffer[rxBuffer.startIndex..<newlineIndex]

            // Trim trailing \r
            if line.last == carriageReturn {
                line = line[line.startIndex..<line.index(before: line.endIndex)]
            }

            if let text = String(data: Data(line), encoding: .utf8), !text.isEmpty {
                messages.append(TerminalMessage(text: text, isOutgoing: false))
            }

            // Advance buffer past the newline
            rxBuffer = Data(rxBuffer[rxBuffer.index(after: newlineIndex)...])
        }
    }

    // MARK: - CBCentralManagerDelegate

    func centralManagerDidUpdateState(_ central: CBCentralManager) {
        switch central.state {
        case .poweredOn:
            startScan()
        case .poweredOff:
            status = "Bluetooth off"
            isConnected = false
        case .unauthorized:
            status = "Bluetooth unauthorized"
        case .unsupported:
            status = "Bluetooth unsupported"
        case .resetting:
            status = "Bluetooth resetting…"
        case .unknown:
            status = "Bluetooth unknown state"
        @unknown default:
            status = "Bluetooth unknown state"
        }
    }

    func centralManager(_ central: CBCentralManager,
                        didDiscover peripheral: CBPeripheral,
                        advertisementData: [String: Any],
                        rssi RSSI: NSNumber) {
        // Connect to the first HM-10 advertising FFE0
        central.stopScan()
        self.peripheral = peripheral
        peripheral.delegate = self
        status = "Connecting…"
        central.connect(peripheral, options: nil)
    }

    func centralManager(_ central: CBCentralManager, didConnect peripheral: CBPeripheral) {
        let name = peripheral.name ?? "HM-10"
        status = "Connected to \(name)"
        isConnected = true
        peripheral.discoverServices([serviceUUID])
    }

    func centralManager(_ central: CBCentralManager,
                        didFailToConnect peripheral: CBPeripheral,
                        error: Error?) {
        status = "Failed to connect — tap to rescan"
        isConnected = false
        self.peripheral = nil
        txCharacteristic = nil
    }

    func centralManager(_ central: CBCentralManager,
                        didDisconnectPeripheral peripheral: CBPeripheral,
                        error: Error?) {
        status = "Disconnected — tap to rescan"
        isConnected = false
        self.peripheral = nil
        txCharacteristic = nil
        rxBuffer = Data()

        // Auto-rescan after disconnect
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.0) { [weak self] in
            self?.startScan()
        }
    }

    // MARK: - CBPeripheralDelegate

    func peripheral(_ peripheral: CBPeripheral, didDiscoverServices error: Error?) {
        guard error == nil else {
            status = "Service discovery failed"
            return
        }
        guard let service = peripheral.services?.first(where: { $0.uuid == serviceUUID }) else {
            return
        }
        peripheral.discoverCharacteristics([characteristicUUID], for: service)
    }

    func peripheral(_ peripheral: CBPeripheral,
                    didDiscoverCharacteristicsFor service: CBService,
                    error: Error?) {
        guard error == nil else {
            status = "Characteristic discovery failed"
            return
        }
        guard let characteristic = service.characteristics?.first(where: {
            $0.uuid == characteristicUUID
        }) else { return }

        txCharacteristic = characteristic

        // Subscribe to notifications for incoming data
        if characteristic.properties.contains(.notify) {
            peripheral.setNotifyValue(true, for: characteristic)
        }
    }

    func peripheral(_ peripheral: CBPeripheral,
                    didUpdateValueFor characteristic: CBCharacteristic,
                    error: Error?) {
        guard error == nil, let data = characteristic.value else { return }
        handleIncomingData(data)
    }

    func peripheral(_ peripheral: CBPeripheral,
                    didUpdateNotificationStateFor characteristic: CBCharacteristic,
                    error: Error?) {
        // Notification subscription confirmed — ready to receive
    }
}
