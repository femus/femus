import SwiftUI

// MARK: - ContentView

struct ContentView: View {
    @StateObject private var terminal = BluetoothTerminal()
    @State private var inputText = ""

    private let maxMessageLength = 50

    var body: some View {
        VStack(spacing: 0) {
            statusBar
            Divider()
            messageList
            Divider()
            inputBar
        }
        .background(Color(.systemGroupedBackground))
    }

    // MARK: Status Bar

    private var statusBar: some View {
        HStack(spacing: 8) {
            Circle()
                .fill(statusColor)
                .frame(width: 10, height: 10)

            Text(terminal.status)
                .font(.subheadline)
                .foregroundColor(.primary)
                .lineLimit(1)
                .truncationMode(.tail)

            Spacer()
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 10)
        .background(Color(.secondarySystemGroupedBackground))
        .onTapGesture {
            terminal.rescan()
        }
    }

    private var statusColor: Color {
        if terminal.isConnected { return .green }
        if terminal.status.hasPrefix("Scanning") { return .yellow }
        if terminal.status.hasPrefix("Connecting") { return .orange }
        return .red
    }

    // MARK: Message List

    private var messageList: some View {
        ScrollViewReader { proxy in
            ScrollView {
                LazyVStack(spacing: 8) {
                    ForEach(terminal.messages) { message in
                        MessageBubble(message: message)
                            .id(message.id)
                    }
                }
                .padding(.horizontal, 12)
                .padding(.vertical, 8)
            }
            .onChange(of: terminal.messages.count) { _ in
                if let last = terminal.messages.last {
                    withAnimation(.easeOut(duration: 0.2)) {
                        proxy.scrollTo(last.id, anchor: .bottom)
                    }
                }
            }
        }
    }

    // MARK: Input Bar

    private var inputBar: some View {
        HStack(spacing: 8) {
            TextField("Message (max \(maxMessageLength) chars)", text: $inputText)
                .textFieldStyle(.roundedBorder)
                .submitLabel(.send)
                .onChange(of: inputText) { newValue in
                    if newValue.count > maxMessageLength {
                        inputText = String(newValue.prefix(maxMessageLength))
                    }
                }
                .onSubmit {
                    sendMessage()
                }

            Button(action: sendMessage) {
                Image(systemName: "paperplane.fill")
                    .foregroundColor(.white)
                    .padding(.horizontal, 12)
                    .padding(.vertical, 8)
                    .background(canSend ? Color.blue : Color.gray)
                    .cornerRadius(8)
            }
            .disabled(!canSend)
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .background(Color(.secondarySystemGroupedBackground))
    }

    private var canSend: Bool {
        terminal.isConnected && !inputText.trimmingCharacters(in: .whitespaces).isEmpty
    }

    private func sendMessage() {
        let trimmed = inputText.trimmingCharacters(in: .whitespaces)
        guard !trimmed.isEmpty, terminal.isConnected else { return }
        terminal.send(trimmed)
        inputText = ""
    }
}

// MARK: - MessageBubble

struct MessageBubble: View {
    let message: TerminalMessage

    var body: some View {
        HStack {
            if message.isOutgoing { Spacer(minLength: 40) }

            Text(message.text)
                .padding(.horizontal, 12)
                .padding(.vertical, 8)
                .background(message.isOutgoing ? Color.blue : Color(.systemGray5))
                .foregroundColor(message.isOutgoing ? .white : .primary)
                .cornerRadius(16)
                .font(.body)

            if !message.isOutgoing { Spacer(minLength: 40) }
        }
    }
}

// MARK: - Preview

#Preview {
    ContentView()
}
