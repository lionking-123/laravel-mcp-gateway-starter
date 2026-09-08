<?php

namespace App\Mcp\Presenters;

use App\Mcp\Support\Money;
use App\Models\Invoice;
use App\Models\ServiceJob;

/**
 * The allow-list for jobs. internal_notes is deliberately absent.
 */
final class JobPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(ServiceJob $job): array
    {
        return [
            'id' => $job->id,
            'title' => $job->title,
            'status' => $job->status,
            'technician' => $job->technician,
            'scheduled_for' => $job->scheduled_for?->toDateString(),
            'completed_at' => $job->completed_at?->toIso8601String(),
            'amount_cents' => $job->amount_cents,
            'amount_display' => Money::display($job->amount_cents),
            'customer' => $job->relationLoaded('customer') && $job->customer
                ? CustomerPresenter::public($job->customer)
                : ['id' => $job->customer_id],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(ServiceJob $job): array
    {
        return self::summary($job) + [
            'created_at' => $job->created_at?->toIso8601String(),
            'updated_at' => $job->updated_at?->toIso8601String(),
            'invoices' => $job->invoices
                ->map(fn (Invoice $invoice) => InvoicePresenter::summary($invoice))
                ->values()
                ->all(),
        ];
    }
}
