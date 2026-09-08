<?php

namespace App\Mcp\Exceptions;

use RuntimeException;

/**
 * A failure the model should hear about: not found, out of range, refused.
 * Rendered as an isError tool result, never as an HTTP or JSON-RPC error.
 */
class ToolException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        string $message,
        public readonly string $errorCode = 'tool_error',
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }
}
