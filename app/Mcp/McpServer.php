<?php

namespace App\Mcp;

use App\Mcp\Audit\AuditLogger;
use App\Mcp\Exceptions\JsonRpcException;
use App\Mcp\Exceptions\ToolException;
use stdClass;
use Throwable;

/**
 * The MCP server core: one JSON-RPC 2.0 message in, one response (or none) out.
 *
 * Transport-agnostic. The HTTP controller decodes the body and builds the
 * ToolContext; this class knows nothing about HTTP.
 */
final class McpServer
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly SchemaValidator $validator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $message  A decoded JSON-RPC 2.0 message.
     * @return array<string, mixed>|null The response, or null for notifications.
     */
    public function handle(array $message, ToolContext $context): ?array
    {
        if (($message['jsonrpc'] ?? null) !== '2.0' || ! is_string($message['method'] ?? null)) {
            throw new JsonRpcException(JsonRpcException::INVALID_REQUEST, 'Expected a JSON-RPC 2.0 request with a string "method".');
        }

        $isNotification = ! array_key_exists('id', $message);
        $id = $message['id'] ?? null;
        $method = $message['method'];
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        if (str_starts_with($method, 'notifications/')) {
            return null;
        }

        try {
            $result = match ($method) {
                'initialize' => $this->initialize($params),
                'ping' => new stdClass,
                'tools/list' => $this->listTools($context),
                'tools/call' => $this->callTool($params, $context),
                default => throw new JsonRpcException(JsonRpcException::METHOD_NOT_FOUND, "Method not found: {$method}"),
            };
        } catch (JsonRpcException $e) {
            return $isNotification ? null : ['jsonrpc' => '2.0', 'id' => $id, 'error' => $e->toError()];
        }

        return $isNotification ? null : ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function initialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;
        $supported = config('mcp.server.supported_protocol_versions', []);

        return [
            'protocolVersion' => in_array($requested, $supported, true) ? $requested : config('mcp.server.protocol_version'),
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => config('mcp.server.name'),
                'title' => config('mcp.server.title'),
                'version' => config('mcp.server.version'),
            ],
            'instructions' => config('mcp.server.instructions'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listTools(ToolContext $context): array
    {
        return [
            'tools' => array_map(fn (Tool $tool) => $tool->definition(), $this->registry->availableFor($context)),
        ];
    }

    /**
     * Protocol problems (unknown tool, invalid arguments) become JSON-RPC errors.
     * Everything the tool itself refuses or fails becomes an isError result the
     * model can read and act on. That split follows the MCP specification.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function callTool(array $params, ToolContext $context): array
    {
        $startedAt = microtime(true);
        $name = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        if (! is_string($name) || $name === '') {
            throw new JsonRpcException(JsonRpcException::INVALID_PARAMS, 'tools/call requires a string "name".');
        }

        if (! is_array($arguments)) {
            throw new JsonRpcException(JsonRpcException::INVALID_PARAMS, '"arguments" must be an object.');
        }

        $tool = $this->registry->get($name);

        if ($tool === null) {
            $this->audit->record($context, $name, $arguments, AuditLogger::UNKNOWN_TOOL, 0, $startedAt);

            throw new JsonRpcException(JsonRpcException::INVALID_PARAMS, "Unknown tool: {$name}");
        }

        if (! $this->registry->isEnabled($tool)) {
            $this->audit->record($context, $name, $arguments, AuditLogger::DENIED_DISABLED, 0, $startedAt, 'writes_disabled');

            return ToolResult::error(
                'Write tools are disabled on this server. An operator must set MCP_WRITES_ENABLED=true; nothing the caller does can enable them.',
                'writes_disabled',
            )->toMcp();
        }

        if (! $context->hasScope($tool->scope())) {
            $this->audit->record($context, $name, $arguments, AuditLogger::DENIED_SCOPE, 0, $startedAt, 'insufficient_scope');

            return ToolResult::error(
                "This token does not carry the {$tool->scope()} scope required by {$name}.",
                'insufficient_scope',
                ['required_scope' => $tool->scope()],
            )->toMcp();
        }

        $problems = $this->validator->validate($tool->inputSchema(), $arguments);

        if ($problems !== []) {
            $this->audit->record($context, $name, $arguments, AuditLogger::INVALID_INPUT, 0, $startedAt, 'invalid_input');

            throw new JsonRpcException(
                JsonRpcException::INVALID_PARAMS,
                'Invalid arguments for '.$name.': '.implode('; ', $problems),
                ['errors' => $problems],
            );
        }

        try {
            $result = $tool->handle($arguments, $context);
        } catch (ToolException $e) {
            $result = ToolResult::error($e->getMessage(), $e->errorCode, $e->extra);
        } catch (Throwable $e) {
            report($e);
            $result = ToolResult::error(
                "The tool failed unexpectedly. The error was logged under request id {$context->requestId}.",
                'internal_error',
            );
        }

        $payload = $result->toMcp();
        $bytes = strlen((string) json_encode($payload));

        if (! $result->isError && $bytes > (int) config('mcp.limits.max_result_bytes')) {
            $result = ToolResult::error(
                'The result is too large to return. Narrow the query with a smaller limit or a shorter date range.',
                'result_too_large',
            );
            $payload = $result->toMcp();
        }

        $this->audit->record(
            $context,
            $name,
            $arguments,
            $result->isError ? AuditLogger::ERROR : AuditLogger::SUCCESS,
            $bytes,
            $startedAt,
            $result->errorCode(),
        );

        return $payload;
    }
}
