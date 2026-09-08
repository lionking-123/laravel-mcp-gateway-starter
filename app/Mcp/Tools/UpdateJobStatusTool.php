<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenters\JobPresenter;
use App\Mcp\Tool;
use App\Mcp\ToolContext;
use App\Mcp\ToolResult;
use App\Models\ServiceJob;

/**
 * The one write tool, included to show the pattern rather than to be used
 * casually. It is hidden and inert until MCP_WRITES_ENABLED=true, needs the
 * write:jobs scope, only allows legal status transitions, and requires the
 * client to pass confirm=true after a human has approved the change.
 */
final class UpdateJobStatusTool extends Tool
{
    public function name(): string
    {
        return 'update_job_status';
    }

    public function title(): ?string
    {
        return 'Update job status';
    }

    public function description(): string
    {
        return 'Move a job to a new status. Allowed transitions: lead→estimate, estimate→scheduled, scheduled→in_progress, '
            .'in_progress→completed, and any open status→cancelled. This changes business data: show the user the job and the '
            .'proposed change, get their explicit approval, then call with confirm=true. Never call it speculatively.';
    }

    public function scope(): string
    {
        return 'write:jobs';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'job_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The job to update.'],
                'status' => ['type' => 'string', 'enum' => ServiceJob::STATUSES, 'description' => 'The new status.'],
                'reason' => ['type' => 'string', 'maxLength' => 200, 'description' => 'Why, in one sentence. Stored in the audit log arguments.'],
                'confirm' => [
                    'type' => 'boolean',
                    'enum' => [true],
                    'description' => 'Must be true. Set it only after a human has approved this exact change.',
                ],
            ],
            'required' => ['job_id', 'status', 'confirm'],
            'additionalProperties' => false,
        ];
    }

    public function handle(array $arguments, ToolContext $context): ToolResult
    {
        $job = ServiceJob::query()->with('customer')->find($arguments['job_id']);

        if ($job === null) {
            $this->fail("No job with id {$arguments['job_id']} exists.", 'not_found');
        }

        $from = $job->status;
        $to = $arguments['status'];
        $allowed = ServiceJob::TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            $this->fail(
                sprintf('A job in status "%s" cannot move to "%s". Allowed: %s.', $from, $to, $allowed === [] ? 'none (terminal status)' : implode(', ', $allowed)),
                'invalid_transition',
                ['from' => $from, 'allowed' => $allowed],
            );
        }

        $job->status = $to;

        if ($to === 'completed') {
            $job->completed_at = now();
        }

        $job->save();

        $data = [
            'job' => JobPresenter::summary($job),
            'changed' => ['status' => ['from' => $from, 'to' => $to]],
            'reason' => $arguments['reason'] ?? null,
        ];

        return ToolResult::ok($data, sprintf('Job #%d moved from %s to %s.', $job->id, $from, $to));
    }
}
