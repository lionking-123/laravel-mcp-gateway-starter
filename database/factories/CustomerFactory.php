<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    private const CITIES = [
        'Hamilton', 'Toronto', 'Mississauga', 'Burlington', 'Oakville', 'Ottawa',
        'London', 'Kitchener', 'Guelph', 'Barrie', 'Calgary', 'Vancouver',
    ];

    public function definition(): array
    {
        $isCompany = fake()->boolean(35);

        return [
            'name' => $isCompany ? fake()->company() : fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('###-###-####'),
            'city' => fake()->randomElement(self::CITIES),
            'internal_notes' => fake()->optional(0.6)->sentence(8),
        ];
    }
}
