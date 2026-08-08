<?php

declare(strict_types=1);

namespace Femus\Mcp;

/** Tool-level failure: reported to the client as an isError tool result, not a protocol error. */
final class McpToolException extends \RuntimeException
{
}
