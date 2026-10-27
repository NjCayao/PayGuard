<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'owner_whatsapp' => '51'.fake()->numerify('#########'),
            'match_window_minutes' => 10,
            'recheck_minutes' => 2,
            'timezone' => 'America/Lima',
        ];
    }
}
