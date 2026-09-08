<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Money;
use App\Mcp\Tool;
use App\Mcp\ToolContext;
use App\Mcp\ToolResult;
use App\Models\Invoice;
use Carbon\CarbonImmutable;

final class RevenueSummaryTool extends Tool
{
    public function name(): string
    {
        return 'revenue_summary';
    }

    public function title(): ?string
    {
        return 'Revenue summary';
    }

    public function description(): string
    {
        return 'Aggregate invoice revenue for a date range. basis "paid" (default) counts invoices by the date they were paid; '
            .'basis "invoiced" counts invoices by issue date regardless of payment. Group totals by month, technician or job_status. '
            .'Also returns the outstanding balance (sent + overdue invoices) as of the end date. '
            .'Returns totals only, never individual customers. Amounts are integer CAD cents. Maximum range: 366 days.';
    }

    public function scope(): string
    {
        return 'read:revenue';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from' => ['type' => 'string', 'format' => 'date', 'description' => 'Start date, inclusive (YYYY-MM-DD).'],
                'to' => ['type' => 'string', 'format' => 'date', 'description' => 'End date, inclusive (YYYY-MM-DD).'],
                'group_by' => [
                    'type' => 'string',
                    'enum' => ['month', 'technician', 'job_status'],
                    'description' => 'How to break the total down. Default: month.',
                ],
                'basis' => [
                    'type' => 'string',
                    'enum' => ['paid', 'invoiced'],
                    'description' => 'paid = by payment date (cash basis); invoiced = by issue date. Default: paid.',
                ],
            ],
            'required' => ['from', 'to'],
            'additionalProperties' => false,
        ];
    }

    public function handle(array $arguments, ToolContext $context): ToolResult
    {
        $from = CarbonImmutable::parse($arguments['from'])->startOfDay();
        $to = CarbonImmutable::parse($arguments['to'])->endOfDay();
        $groupBy = $arguments['group_by'] ?? 'month';
        $basis = $arguments['basis'] ?? 'paid';

        if ($from->greaterThan($to)) {
            $this->fail('"from" must be on or before "to".', 'invalid_range');
        }

        $maxDays = (int) config('mcp.limits.max_revenue_range_days');

        if ($from->diffInDays($to) > $maxDays) {
            $this->fail("The range may span at most {$maxDays} days.", 'range_too_large');
        }

        $dateColumn = $basis === 'paid' ? 'invoices.paid_at' : 'invoices.issued_at';

        $rows = Invoice::query()
            ->join('service_jobs', 'service_jobs.id', '=', 'invoices.service_job_id')
            ->when($basis === 'paid', fn ($q) => $q->where('invoices.status', 'paid'))
            ->when($basis === 'invoiced', fn ($q) => $q->whereNotIn('invoices.status', ['draft', 'void']))
            ->whereBetween($dateColumn, [$from->toDateString(), $to->toDateString()])
            ->get([
                'invoices.amount_cents',
                'invoices.paid_at',
                'invoices.issued_at',
                'service_jobs.technician',
                'service_jobs.status as job_status',
            ]);

        $groups = [];

        foreach ($rows as $row) {
            $key = match ($groupBy) {
                'technician' => $row->technician ?? 'unassigned',
                'job_status' => $row->job_status,
                default => ($basis === 'paid' ? $row->paid_at : $row->issued_at)->format('Y-m'),
            };

            $groups[$key] ??= ['key' => $key, 'total_cents' => 0, 'invoice_count' => 0];
            $groups[$key]['total_cents'] += (int) $row->amount_cents;
            $groups[$key]['invoice_count']++;
        }

        ksort($groups);

        $groups = array_values(array_map(function (array $g) {
            $g['total_display'] = Money::display($g['total_cents']);

            return $g;
        }, $groups));

        $totalCents = array_sum(array_column($groups, 'total_cents'));

        $outstandingCents = (int) Invoice::query()
            ->whereIn('status', ['sent', 'overdue'])
            ->whereDate('issued_at', '<=', $to->toDateString())
            ->sum('amount_cents');

        $data = [
            'basis' => $basis,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'currency' => Money::CURRENCY,
            'total_cents' => $totalCents,
            'total_display' => Money::display($totalCents),
            'invoice_count' => $rows->count(),
            'group_by' => $groupBy,
            'groups' => $groups,
            'outstanding_cents' => $outstandingCents,
            'outstanding_display' => Money::display($outstandingCents),
        ];

        $lines = array_map(
            fn (array $g) => sprintf('%s: %s (%d %s)', $g['key'], $g['total_display'], $g['invoice_count'], $g['invoice_count'] === 1 ? 'invoice' : 'invoices'),
            $groups,
        );

        $text = sprintf(
            "Revenue (%s basis) %s to %s: %s across %d invoices.\nBy %s:\n%s\nOutstanding (sent + overdue) as of %s: %s",
            $basis,
            $data['from'],
            $data['to'],
            $data['total_display'],
            $data['invoice_count'],
            $groupBy,
            $lines === [] ? '(none)' : implode("\n", $lines),
            $data['to'],
            $data['outstanding_display'],
        );

        return ToolResult::ok($data, $text);
    }
}
