<?php

namespace App\Models;

use Database\Factories\ServiceJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A field-service job. Named ServiceJob so it never collides with Laravel's
 * queue "jobs" table; the tools still call these "jobs".
 */
class ServiceJob extends Model
{
    /** @use HasFactory<ServiceJobFactory> */
    use HasFactory;

    public const STATUSES = ['lead', 'estimate', 'scheduled', 'in_progress', 'completed', 'cancelled'];

    /** Which status may move to which. Used by the write tool. */
    public const TRANSITIONS = [
        'lead' => ['estimate', 'cancelled'],
        'estimate' => ['scheduled', 'cancelled'],
        'scheduled' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    protected $fillable = [
        'customer_id', 'title', 'status', 'technician', 'scheduled_for',
        'completed_at', 'amount_cents', 'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'completed_at' => 'datetime',
            'amount_cents' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
