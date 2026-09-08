<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fake but coherent data: customers with jobs, jobs with invoices whose dates
 * and amounts line up. Seeded deterministically so screenshots reproduce.
 */
class DemoDataSeeder extends Seeder
{
    public const DEMO_EMAIL = 'demo@example.com';

    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        fake()->seed(2026);

        User::query()->firstOrCreate(
            ['email' => self::DEMO_EMAIL],
            ['name' => 'Demo Manager', 'password' => self::DEMO_PASSWORD],
        );

        if (Customer::query()->exists()) {
            $this->command?->info('Demo data already present; skipping.');

            return;
        }

        Customer::factory()
            ->count(30)
            ->create()
            ->each(function (Customer $customer): void {
                ServiceJob::factory()
                    ->count(fake()->numberBetween(1, 4))
                    ->for($customer)
                    ->create()
                    ->each(function (ServiceJob $job): void {
                        if (in_array($job->status, ['completed', 'in_progress'], true)) {
                            Invoice::factory()->forJob($job)->create();
                        }
                    });
            });

        $this->command?->info(sprintf(
            'Seeded %d customers, %d jobs, %d invoices.',
            Customer::query()->count(),
            ServiceJob::query()->count(),
            Invoice::query()->count(),
        ));
    }
}
