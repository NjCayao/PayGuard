<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Cashier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cashier>
 */
class CashierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->name(),
            'whatsapp' => '51'.fake()->unique()->numerify('#########'),
            'is_active' => true,
        ];
    }
}
