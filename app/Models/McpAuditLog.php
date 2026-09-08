<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per tools/call attempt. Append-only: there is no updated_at and
 * nothing in the application edits these rows after creation.
 */
class McpAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'request_id', 'user_id', 'client_id', 'token_id', 'tool', 'arguments',
        'status', 'error_code', 'result_bytes', 'duration_ms', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'result_bytes' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
