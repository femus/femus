<?php

declare(strict_types=1);

namespace Femus\Mcp;

/**
 * Minimal MCP (Model Context Protocol) server over newline-delimited JSON-RPC 2.0.
 * Exposes the tools capability only — enough for an AI agent to drive hardware.
 */
final class McpServer
{
    /**
     * @param resource $input
     * @param resource $output
     */
    public function __construct(
        private $input,
        private $output,
        private readonly ToolRegistry $tools,
        private readonly string $name = 'femus',
        private readonly string $version = '0.1.0',
    ) {
    }

    public function run(): void
    {
        while (($line = fgets($this->input)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $message = json_decode($line, true);
            if (!is_array($message)) {
                $this->reply(null, error: ['code' => -32700, 'message' => 'Parse error']);
                continue;
            }

            $this->dispatch($message);
        }
    }

    /** @param array<string, mixed> $message */
    private function dispatch(array $message): void
    {
        $id = $message['id'] ?? null;
        $method = (string) ($message['method'] ?? '');
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        if ($id === null) {
            return; // notifications need no response
        }

        match ($method) {
            'initialize' => $this->reply($id, [
                'protocolVersion' => (string) ($params['protocolVersion'] ?? '2025-03-26'),
                'capabilities' => ['tools' => new \stdClass()],
                'serverInfo' => ['name' => $this->name, 'version' => $this->version],
            ]),
            'ping' => $this->reply($id, new \stdClass()),
            'tools/list' => $this->reply($id, ['tools' => $this->tools->list()]),
            'tools/call' => $this->handleToolCall($id, $params),
            default => $this->reply($id, error: ['code' => -32601, 'message' => "Method not found: {$method}"]),
        };
    }

    /** @param array<string, mixed> $params */
    private function handleToolCall(mixed $id, array $params): void
    {
        $name = (string) ($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if (!$this->tools->has($name)) {
            $this->reply($id, error: ['code' => -32602, 'message' => "Unknown tool: {$name}"]);

            return;
        }

        try {
            $text = $this->tools->call($name, $arguments);
            $this->reply($id, [
                'content' => [['type' => 'text', 'text' => $text]],
                'isError' => false,
            ]);
        } catch (\Throwable $e) {
            $this->reply($id, [
                'content' => [['type' => 'text', 'text' => $e->getMessage()]],
                'isError' => true,
            ]);
        }
    }

    /** @param array<string, mixed>|\stdClass|null $result */
    private function reply(mixed $id, mixed $result = null, ?array $error = null): void
    {
        $response = ['jsonrpc' => '2.0', 'id' => $id];
        if ($error !== null) {
            $response['error'] = $error;
        } else {
            $response['result'] = $result;
        }

        fwrite($this->output, json_encode($response, JSON_UNESCAPED_SLASHES) . "\n");
    }
}
