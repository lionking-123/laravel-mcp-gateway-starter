<?php

namespace App\Mcp;

/**
 * What a tool hands back. Successful results carry structured data (returned
 * as structuredContent) plus a text rendering for clients that only read text.
 * Errors are returned to the model as isError results so it can self-correct.
 */
final class ToolResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public readonly array $data,
        public readonly string $text,
        public readonly bool $isError,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function ok(array $data, ?string $text = null): self
    {
        return new self(
            $data,
            $text ?? (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            false,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function error(string $message, string $code = 'tool_error', array $extra = []): self
    {
        return new self(
            ['error' => ['code' => $code, 'message' => $message] + $extra],
            $message,
            true,
        );
    }

    public function errorCode(): ?string
    {
        return $this->isError ? ($this->data['error']['code'] ?? 'tool_error') : null;
    }

    /**
     * The MCP tools/call result shape.
     *
     * @return array<string, mixed>
     */
    public function toMcp(): array
    {
        $result = [
            'content' => [
                ['type' => 'text', 'text' => $this->text],
            ],
            'isError' => $this->isError,
        ];

        if (! $this->isError) {
            $result['structuredContent'] = $this->data;
        }

        return $result;
    }
}
