<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Cashier;
use App\Models\OwnerAlert;
use App\Models\Verification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnerAlert>
 */
class OwnerAlertFactory extends Factory
{
    public function definition(): array
    {
        $business = Business::factory();

        return [
            'business_id' => $business,
            'verification_id' => Verification::factory()
                ->for($business, 'business')
                ->for(Cashier::factory()->for($business, 'business')),
            'to' => '51'.fake()->numerify('#########'),
            'template' => 'alerta_pago_sospechoso',
            'reason' => 'Esta captura ya se usó antes.',
            'wa_message_id' => fake()->unique()->bothify('wamid.####????????'),
            'status' => 'sent',
            'error' => null,
        ];
    }
}
