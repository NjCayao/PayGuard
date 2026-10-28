<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Cashier;
use App\Models\Verification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Verification>
 */
class VerificationFactory extends Factory
{
    public function definition(): array
    {
        $business = Business::factory();

        return [
            'business_id' => $business,
            'cashier_id' => Cashier::factory()->for($business, 'business'),
            'wa_message_id' => fake()->unique()->bothify('wamid.####????????'),
            'input_type' => 'image',
            'media_path' => null,
            'media_sha256' => null,
            'transcript' => null,
            'extracted' => null,
            'wallet' => null,
            'amount' => null,
            'recipient_name' => null,
            'security_code' => null,
            'paid_at' => null,
            'operation_code' => null,
            'edit_signals' => null,
            'status' => 'processing',
            'reason' => null,
            'received_payment_id' => null,
            'recheck_until' => null,
            'ai_ms' => null,
        ];
    }

    public function analyzed(): static
    {
        return $this->state(fn (array $attributes) => [
            'media_path' => 'whatsapp/1/1.jpg',
            'media_sha256' => hash('sha256', fake()->unique()->uuid()),
            'extracted' => ['wallet' => 'yape'],
            'wallet' => 'yape',
            'amount' => '50.00',
            'recipient_name' => 'Maria Torres',
            'paid_at' => now(),
        ]);
    }
}
