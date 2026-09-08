<?php

namespace App\Mcp;

use App\Mcp\Exceptions\ToolException;

/**
 * One capability exposed to the model.
 *
 * A tool is the only way the model reaches your data. It declares what it is
 * called, what it does, what input it accepts, and which OAuth scope a token
 * must carry. The gateway validates input against the schema and checks the
 * scope before handle() runs, so handle() can trust its arguments.
 */
abstract class Tool
{
    /**
     * Unique name in snake_case. This is what the model calls.
     */
    abstract public function name(): string;

    /**
     * Plain-language description. The model reads this to decide when to use
     * the tool, so say what it returns, what it does not return, and any units.
     */
    abstract public function description(): string;

    /**
     * JSON Schema for the arguments (the subset in App\Mcp\SchemaValidator).
     * Always set "additionalProperties" to false so unknown keys are rejected.
     *
     * @return array<string, mixed>
     */
    abstract public function inputSchema(): array;

    /**
     * The single OAuth scope required to list and call this tool.
     * Must exist in config('mcp.scopes').
     */
    abstract public function scope(): string;

    /**
     * Do the work. Arguments have already been validated against inputSchema().
     *
     * @param  array<string, mixed>  $arguments
     */
    abstract public function handle(array $arguments, ToolContext $context): ToolResult;

    /**
     * Optional human-readable title some clients display instead of the name.
     */
    public function title(): ?string
    {
        return null;
    }

    /**
     * Whether this tool changes data. Derived from the scope prefix by default.
     */
    public function isWrite(): bool
    {
        return str_starts_with($this->scope(), 'write:');
    }

    /**
     * MCP tool annotations. These are hints for the client UI, not security
     * boundaries; the scope check is what actually protects the data.
     *
     * @return array<string, bool>
     */
    public function annotations(): array
    {
        return [
            'readOnlyHint' => ! $this->isWrite(),
            'destructiveHint' => $this->isWrite(),
            'idempotentHint' => ! $this->isWrite(),
            'openWorldHint' => false,
        ];
    }

    /**
     * Optional JSON Schema describing structuredContent in the result.
     *
     * @return array<string, mixed>|null
     */
    public function outputSchema(): ?array
    {
        return null;
    }

    /**
     * The definition sent in tools/list.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $definition = [
            'name' => $this->name(),
            'description' => $this->description(),
            'inputSchema' => $this->inputSchema(),
            'annotations' => $this->annotations(),
        ];

        if ($this->title() !== null) {
            $definition['title'] = $this->title();
        }

        if ($this->outputSchema() !== null) {
            $definition['outputSchema'] = $this->outputSchema();
        }

        return $definition;
    }

    /**
     * Abort with a tool-level error the model can read and recover from.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function fail(string $message, string $code = 'tool_error', array $extra = []): never
    {
        throw new ToolException($message, $code, $extra);
    }
}
