<?php

namespace App\Mcp\Exceptions;

use RuntimeException;

/**
 * A protocol-level failure: malformed request, unknown method, unknown tool,
 * invalid arguments. Rendered as a JSON-RPC 2.0 error object.
 */
class JsonRpcException extends RuntimeException
{
    public const PARSE_ERROR = -32700;

    public const INVALID_REQUEST = -32600;

    public const METHOD_NOT_FOUND = -32601;

    public const INVALID_PARAMS = -32602;

    public const INTERNAL_ERROR = -32603;

    public function __construct(
        int $code,
        string $message,
        public readonly mixed $data = null,
    ) {
        parent::__construct($message, $code);
    }

    /**
     * @return array<string, mixed>
     */
    public function toError(): array
    {
        $error = ['code' => $this->getCode(), 'message' => $this->getMessage()];

        if ($this->data !== null) {
            $error['data'] = $this->data;
        }

        return $error;
    }
}
