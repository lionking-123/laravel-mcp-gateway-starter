<?php

namespace App\Mcp\Audit;

use App\Mcp\ToolContext;
use App\Models\McpAuditLog;

/**
 * Records every tools/call attempt, including the ones that were refused.
 *
 * This is fail-closed on purpose: if the audit row cannot be written the
 * exception propagates and the caller receives an internal error. A gateway
 * that answers questions it cannot account for is not a gateway.
 */
final class AuditLogger
{
    public const SUCCESS = 'success';

    public const ERROR = 'error';

    public const DENIED_SCOPE = 'denied_scope';

    public const DENIED_DISABLED = 'denied_disabled';

    public const INVALID_INPUT = 'invalid_input';

    public const UNKNOWN_TOOL = 'unknown_tool';

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function record(
        ToolContext $context,
        string $tool,
        array $arguments,
        string $status,
        int $resultBytes,
        float $startedAt,
        ?string $errorCode = null,
    ): void {
        if (! config('mcp.audit.enabled')) {
            return;
        }

        McpAuditLog::query()->create([
            'request_id' => $context->requestId,
            'user_id' => $context->userId(),
            'client_id' => $context->clientId,
            'token_id' => $context->tokenId,
            'tool' => mb_substr($tool, 0, 100),
            'arguments' => config('mcp.audit.log_arguments') ? $arguments : null,
            'status' => $status,
            'error_code' => $errorCode,
            'result_bytes' => $resultBytes,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'ip' => request()->ip(),
        ]);
    }
}
