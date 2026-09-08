<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenters\JobPresenter;
use App\Mcp\Tool;
use App\Mcp\ToolContext;
use App\Mcp\ToolResult;
use App\Models\ServiceJob;

final class GetJobTool extends Tool
{
    public function name(): string
    {
        return 'get_job';
    }

    public function title(): ?string
    {
        return 'Get one job';
    }

    public function description(): string
    {
        return 'Fetch one field-service job by id, including its customer (name and city only) and its invoices '
            .'with number, status, amount and dates. Use list_jobs first if you do not know the id.';
    }

    public function scope(): string
    {
        return 'read:jobs';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'job_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'The job id as returned by list_jobs.',
                ],
            ],
            'required' => ['job_id'],
            'additionalProperties' => false,
        ];
    }

    public function handle(array $arguments, ToolContext $context): ToolResult
    {
        $job = ServiceJob::query()
            ->with(['customer', 'invoices' => fn ($q) => $q->orderBy('issued_at')])
            ->find($arguments['job_id']);

        if ($job === null) {
            $this->fail("No job with id {$arguments['job_id']} exists.", 'not_found');
        }

        $data = JobPresenter::detail($job);

        $text = sprintf(
            "Job #%d: %s\nStatus: %s | Technician: %s | Scheduled: %s | Amount: %s\nCustomer: %s (%s)\nInvoices: %s",
            $data['id'],
            $data['title'],
            $data['status'],
            $data['technician'] ?? 'unassigned',
            $data['scheduled_for'] ?? 'not scheduled',
            $data['amount_display'],
            $data['customer']['name'] ?? 'unknown',
            $data['customer']['city'] ?? 'unknown city',
            $data['invoices'] === []
                ? 'none'
                : implode('; ', array_map(
                    fn (array $i) => sprintf('%s %s %s', $i['number'], $i['status'], $i['amount_display']),
                    $data['invoices'],
                )),
        );

        return ToolResult::ok($data, $text);
    }
}
