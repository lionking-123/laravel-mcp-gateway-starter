<?php

namespace App\Mcp\Presenters;

use App\Mcp\Support\Money;
use App\Models\Invoice;

final class InvoicePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status,
            'amount_cents' => $invoice->amount_cents,
            'amount_display' => Money::display($invoice->amount_cents),
            'issued_at' => $invoice->issued_at?->toDateString(),
            'due_at' => $invoice->due_at?->toDateString(),
            'paid_at' => $invoice->paid_at?->toDateString(),
        ];
    }
}
