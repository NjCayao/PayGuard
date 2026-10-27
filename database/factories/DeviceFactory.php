<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->randomElement(['Xiaomi', 'Samsung', 'Motorola']).' '.fake()->bothify('Model ##'),
            'last_seen_at' => null,
            'last_payment_at' => null,
        ];
    }
}
