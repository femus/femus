<?php

declare(strict_types=1);

namespace Femus\Mcp;

final class ToolRegistry
{
    /** @var array<string, array{description: string, inputSchema: array<string, mixed>, handler: callable}> */
    private array $tools = [];

    /**
     * @param array<string, mixed> $inputSchema JSON Schema for the tool arguments
     * @param callable(array<string, mixed>): string $handler returns the text result
     */
    public function register(string $name, string $description, array $inputSchema, callable $handler): void
    {
        $this->tools[$name] = [
            'description' => $description,
            'inputSchema' => $inputSchema,
            'handler' => $handler,
        ];
    }

    /** @return list<array{name: string, description: string, inputSchema: array<string, mixed>}> */
    public function list(): array
    {
        $out = [];
        foreach ($this->tools as $name => $tool) {
            $out[] = [
                'name' => $name,
                'description' => $tool['description'],
                'inputSchema' => $tool['inputSchema'],
            ];
        }

        return $out;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /** @param array<string, mixed> $arguments */
    public function call(string $name, array $arguments): string
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Unknown tool: {$name}");
        }

        return ($this->tools[$name]['handler'])($arguments);
    }
}
