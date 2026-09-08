<?php

use App\Mcp\Tools\GetJobTool;
use App\Mcp\Tools\ListJobsTool;
use App\Mcp\Tools\RevenueSummaryTool;
use App\Mcp\Tools\UpdateJobStatusTool;

return [

    /*
    |--------------------------------------------------------------------------
    | Server identity
    |--------------------------------------------------------------------------
    |
    | Sent to clients in the MCP "initialize" response. The protocol version
    | is the newest revision this server implements; older revisions in the
    | supported list are accepted when a client asks for them.
    |
    */

    'server' => [
        'name' => env('MCP_SERVER_NAME', 'laravel-mcp-gateway-starter'),
        'title' => env('MCP_SERVER_TITLE', 'Laravel MCP Gateway Starter'),
        'version' => '1.0.0',
        'protocol_version' => '2025-06-18',
        'supported_protocol_versions' => ['2025-06-18', '2025-03-26', '2024-11-05'],
        'instructions' => 'Read-only gateway over a sample field-service dataset (customers, jobs, invoices). '
            .'Money is in integer CAD cents. Use list_jobs to find jobs, get_job for one job with its invoices, '
            .'and revenue_summary for totals over a date range.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    |
    | Every tool declares exactly one scope. A token carries a subset of these
    | and can only list and call tools whose scope it holds. Scopes starting
    | with "write:" mark tools that change data; see "writes_enabled".
    |
    */

    'scopes' => [
        'read:jobs' => 'Read customers and field-service jobs (allow-listed fields only)',
        'read:revenue' => 'Read aggregated invoice and revenue figures',
        'write:jobs' => 'Change the status of a job (disabled unless MCP_WRITES_ENABLED=true)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Writes
    |--------------------------------------------------------------------------
    |
    | Write tools are registered so they show up in code review, but they are
    | hidden from tools/list and refuse to run until this flag is true. Turning
    | it on is a deliberate deployment decision, not a per-request one.
    |
    */

    'writes_enabled' => (bool) env('MCP_WRITES_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Tools
    |--------------------------------------------------------------------------
    |
    | The full catalogue. Add a class here to expose a new tool. Nothing else
    | is discovered automatically: what is exposed to the model is exactly
    | this list, and nothing more.
    |
    */

    'tools' => [
        ListJobsTool::class,
        GetJobTool::class,
        RevenueSummaryTool::class,
        UpdateJobStatusTool::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits (per access token)
    |--------------------------------------------------------------------------
    */

    'rate_limit' => [
        'per_minute' => (int) env('MCP_RATE_PER_MINUTE', 60),
        'per_day' => (int) env('MCP_RATE_PER_DAY', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit log
    |--------------------------------------------------------------------------
    |
    | Every tools/call attempt is recorded: who (user, client, token), which
    | tool, the arguments, the outcome, the result size and duration. Turn
    | "log_arguments" off if tool inputs may carry personal data you must not
    | retain. Prune with "php artisan mcp:audit:prune".
    |
    */

    'audit' => [
        'enabled' => (bool) env('MCP_AUDIT_ENABLED', true),
        'log_arguments' => (bool) env('MCP_AUDIT_LOG_ARGUMENTS', true),
        'retention_days' => (int) env('MCP_AUDIT_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token lifetimes
    |--------------------------------------------------------------------------
    */

    'tokens' => [
        'access_ttl_minutes' => (int) env('MCP_ACCESS_TOKEN_TTL_MINUTES', 60),
        'refresh_ttl_days' => (int) env('MCP_REFRESH_TOKEN_TTL_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth client registration
    |--------------------------------------------------------------------------
    |
    | MCP clients such as Claude.ai and MCP Inspector register themselves at
    | POST /oauth/register (RFC 7591). Only public clients with PKCE are
    | created. Leave "allowed_redirect_hosts" empty to accept any https
    | redirect URI (plus http on localhost); list hosts to lock it down.
    |
    */

    'oauth' => [
        'dynamic_registration' => (bool) env('MCP_DYNAMIC_REGISTRATION', true),
        'allowed_redirect_hosts' => array_filter(explode(',', (string) env('MCP_ALLOWED_REDIRECT_HOSTS', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Result limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'max_page_size' => 50,
        'max_result_bytes' => 200_000,
        'max_revenue_range_days' => 366,
    ],

];
