<?php

namespace App\Console\Commands;

use App\Models\McpAuditLog;
use Illuminate\Console\Command;

final class McpAuditPruneCommand extends Command
{
    protected $signature = 'mcp:audit:prune {--days= : Override config mcp.audit.retention_days}';

    protected $description = 'Delete MCP audit log rows older than the retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('mcp.audit.retention_days'));

        if ($days <= 0) {
            $this->components->warn('Retention is disabled (0 days); nothing pruned.');

            return self::SUCCESS;
        }

        $deleted = McpAuditLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->components->info("Pruned {$deleted} audit row(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
