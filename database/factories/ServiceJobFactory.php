<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\ServiceJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceJob>
 */
class ServiceJobFactory extends Factory
{
    protected $model = ServiceJob::class;

    public const TITLES = [
        'Water damage assessment', 'Basement flood cleanup', 'Mould remediation',
        'Fire damage restoration', 'Tile and grout restoration', 'Marble floor polishing',
        'Carpet restoration', 'Hardwood floor refinishing', 'Sewage backup cleanup',
        'Smoke odour removal', 'Shower regrout', 'Countertop resealing',
    ];

    public const TECHNICIANS = ['A. Nguyen', 'M. Okafor', 'S. Patel', 'J. Tremblay', 'L. Romero'];

    public function definition(): array
    {
        $status = fake()->randomElement([
            'lead', 'lead', 'estimate', 'estimate', 'scheduled', 'scheduled',
            'in_progress', 'completed', 'completed', 'completed', 'completed', 'cancelled',
        ]);

        $scheduledFor = in_array($status, ['lead', 'estimate'], true)
            ? null
            : fake()->dateTimeBetween('-11 months', '+6 weeks');

        $completedAt = $status === 'completed' && $scheduledFor
            ? (clone $scheduledFor)->modify('+'.fake()->numberBetween(0, 3).' days')
            : null;

        return [
            'customer_id' => Customer::factory(),
            'title' => fake()->randomElement(self::TITLES),
            'status' => $status,
            'technician' => $status === 'lead' ? null : fake()->randomElement(self::TECHNICIANS),
            'scheduled_for' => $scheduledFor,
            'completed_at' => $completedAt,
            'amount_cents' => fake()->numberBetween(150, 9000) * 100,
            'internal_notes' => fake()->optional(0.5)->sentence(10),
        ];
    }
}
