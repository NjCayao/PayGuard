<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Device;
use App\Models\ReceivedPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceivedPayment>
 */
class ReceivedPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'device_id' => Device::factory(),
            'wallet' => 'yape',
            'source_package' => 'com.bcp.innovacxion.yapeapp',
            'amount' => fake()->randomFloat(2, 1, 500),
            'payer_name' => fake()->name(),
            'security_code' => null,
            'operation_code' => null,
            'received_at' => now(),
            'raw_text' => 'Yape! Test User te envió un pago por S/ 10',
            'parsed' => true,
            'idempotency_key' => fake()->unique()->uuid(),
        ];
    }
}
