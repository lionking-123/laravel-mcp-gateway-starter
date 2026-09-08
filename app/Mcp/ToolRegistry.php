<?php

namespace App\Mcp;

use InvalidArgumentException;

/**
 * The catalogue of tools this server exposes. Built once from
 * config('mcp.tools'); nothing is discovered by convention.
 */
final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    /**
     * @param  iterable<Tool>  $tools
     */
    public function __construct(iterable $tools = [])
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(Tool $tool): void
    {
        $name = $tool->name();

        if (! preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $name)) {
            throw new InvalidArgumentException("Tool name [{$name}] must be 1-64 characters of letters, digits, _ or -.");
        }

        if (isset($this->tools[$name])) {
            throw new InvalidArgumentException("Tool name [{$name}] is registered twice.");
        }

        if (! array_key_exists($tool->scope(), config('mcp.scopes', []))) {
            throw new InvalidArgumentException(
                "Tool [{$name}] declares scope [{$tool->scope()}] which is not defined in config/mcp.php."
            );
        }

        $this->tools[$name] = $tool;
    }

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array<string, Tool>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Write tools stay dark until writes are switched on for the deployment.
     */
    public function isEnabled(Tool $tool): bool
    {
        return ! $tool->isWrite() || (bool) config('mcp.writes_enabled');
    }

    /**
     * Tools the caller may actually use: enabled, and covered by the token's scopes.
     * tools/list returns only these, so the model never sees what it cannot call.
     *
     * @return list<Tool>
     */
    public function availableFor(ToolContext $context): array
    {
        return array_values(array_filter(
            $this->tools,
            fn (Tool $tool) => $this->isEnabled($tool) && $context->hasScope($tool->scope()),
        ));
    }
}
