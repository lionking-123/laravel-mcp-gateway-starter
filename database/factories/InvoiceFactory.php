<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\ServiceJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        $issuedAt = fake()->dateTimeBetween('-11 months', 'now');
        $status = fake()->randomElement(['paid', 'paid', 'paid', 'sent', 'overdue']);

        return [
            'service_job_id' => ServiceJob::factory(),
            'number' => sprintf('INV-%d-%04d', (int) $issuedAt->format('Y'), ++self::$sequence),
            'status' => $status,
            'amount_cents' => fake()->numberBetween(150, 9000) * 100,
            'issued_at' => $issuedAt,
            'due_at' => (clone $issuedAt)->modify('+30 days'),
            'paid_at' => $status === 'paid'
                ? (clone $issuedAt)->modify('+'.fake()->numberBetween(1, 40).' days')
                : null,
        ];
    }

    /**
     * Build an invoice that matches its job's amount and timeline.
     */
    public function forJob(ServiceJob $job): static
    {
        return $this->state(function () use ($job) {
            $anchor = $job->completed_at ?? $job->scheduled_for ?? now()->subDays(30);
            $issuedAt = $anchor->copy()->addDays(fake()->numberBetween(0, 2));
            $status = $job->status === 'completed'
                ? fake()->randomElement(['paid', 'paid', 'paid', 'paid', 'sent', 'overdue'])
                : 'sent';

            if ($status !== 'paid' && $issuedAt->copy()->addDays(30)->isPast()) {
                $status = 'overdue';
            }

            return [
                'service_job_id' => $job->id,
                'amount_cents' => $job->status === 'completed' ? $job->amount_cents : intdiv($job->amount_cents, 2),
                'issued_at' => $issuedAt,
                'due_at' => $issuedAt->copy()->addDays(30),
                'status' => $status,
                'paid_at' => $status === 'paid' ? $issuedAt->copy()->addDays(fake()->numberBetween(1, 35)) : null,
            ];
        });
    }
}
