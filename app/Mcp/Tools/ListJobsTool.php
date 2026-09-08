<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenters\JobPresenter;
use App\Mcp\Tool;
use App\Mcp\ToolContext;
use App\Mcp\ToolResult;
use App\Models\ServiceJob;

final class ListJobsTool extends Tool
{
    public function name(): string
    {
        return 'list_jobs';
    }

    public function title(): ?string
    {
        return 'List jobs';
    }

    public function description(): string
    {
        return 'List field-service jobs, newest first, with optional filters. '
            .'Returns id, title, status, technician, scheduled date, amount and the customer name and city. '
            .'Does NOT return customer contact details or internal notes. '
            .'Statuses: lead, estimate, scheduled, in_progress, completed, cancelled. '
            .'Amounts are integer CAD cents. Page through results with the returned next_cursor.';
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
                'status' => [
                    'type' => 'string',
                    'enum' => ServiceJob::STATUSES,
                    'description' => 'Only jobs in this status.',
                ],
                'customer_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Only jobs for this customer id.',
                ],
                'technician' => [
                    'type' => 'string',
                    'maxLength' => 80,
                    'description' => 'Only jobs assigned to this technician (exact name as returned by other calls).',
                ],
                'scheduled_from' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => 'Only jobs scheduled on or after this date (YYYY-MM-DD).',
                ],
                'scheduled_to' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => 'Only jobs scheduled on or before this date (YYYY-MM-DD).',
                ],
                'search' => [
                    'type' => 'string',
                    'minLength' => 2,
                    'maxLength' => 80,
                    'description' => 'Case-insensitive match against the job title.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => config('mcp.limits.max_page_size'),
                    'description' => 'Page size. Default 20.',
                ],
                'cursor' => [
                    'type' => 'string',
                    'maxLength' => 100,
                    'description' => 'Opaque cursor from a previous response to fetch the next page.',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function handle(array $arguments, ToolContext $context): ToolResult
    {
        $limit = (int) ($arguments['limit'] ?? 20);

        $query = ServiceJob::query()
            ->with('customer')
            ->orderByDesc('id');

        if (isset($arguments['status'])) {
            $query->where('status', $arguments['status']);
        }

        if (isset($arguments['customer_id'])) {
            $query->where('customer_id', $arguments['customer_id']);
        }

        if (isset($arguments['technician'])) {
            $query->where('technician', $arguments['technician']);
        }

        if (isset($arguments['scheduled_from'])) {
            $query->whereDate('scheduled_for', '>=', $arguments['scheduled_from']);
        }

        if (isset($arguments['scheduled_to'])) {
            $query->whereDate('scheduled_for', '<=', $arguments['scheduled_to']);
        }

        if (isset($arguments['search'])) {
            // Parameter binding handles quoting; we only escape LIKE wildcards.
            $needle = addcslashes(mb_strtolower($arguments['search']), '%_\\');
            $query->whereRaw('LOWER(title) LIKE ?', ['%'.$needle.'%']);
        }

        if (isset($arguments['cursor'])) {
            $query->where('id', '<', $this->decodeCursor($arguments['cursor']));
        }

        $jobs = $query->limit($limit + 1)->get();
        $hasMore = $jobs->count() > $limit;
        $jobs = $jobs->take($limit);

        $items = $jobs->map(fn (ServiceJob $job) => JobPresenter::summary($job))->values()->all();

        $data = [
            'items' => $items,
            'count' => count($items),
            'next_cursor' => $hasMore ? $this->encodeCursor((int) $jobs->last()->id) : null,
            'filters' => array_intersect_key($arguments, array_flip([
                'status', 'customer_id', 'technician', 'scheduled_from', 'scheduled_to', 'search',
            ])),
        ];

        return ToolResult::ok($data, $this->summarize($items, $hasMore));
    }

    private function encodeCursor(int $lastId): string
    {
        return rtrim(strtr(base64_encode('id:'.$lastId), '+/', '-_'), '=');
    }

    private function decodeCursor(string $cursor): int
    {
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);

        if ($decoded === false || ! preg_match('/^id:(\d+)$/', $decoded, $m)) {
            $this->fail('The cursor is not valid. Use the next_cursor value from a previous response.', 'invalid_cursor');
        }

        return (int) $m[1];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function summarize(array $items, bool $hasMore): string
    {
        if ($items === []) {
            return 'No jobs match these filters.';
        }

        $lines = array_map(
            fn (array $j) => sprintf(
                '#%d %s | %s | %s | %s | %s',
                $j['id'],
                $j['title'],
                $j['status'],
                $j['customer']['name'] ?? 'customer '.$j['customer']['id'],
                $j['scheduled_for'] ?? 'unscheduled',
                $j['amount_display'],
            ),
            $items,
        );

        return count($items).' job(s)'.($hasMore ? ' (more available; pass next_cursor)' : '').":\n".implode("\n", $lines);
    }
}
