<?php

namespace Tests\Feature\Panel;

use App\Models\Cashier;
use App\Models\OwnerAlert;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OwnerAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_lists_only_own_alerts_with_expected_fields(): void
    {
        $owner = User::factory()->create();
        $cashier = Cashier::factory()->create(['business_id' => $owner->business_id]);
        $verification = Verification::factory()->for($owner->business)->for($cashier)->create([
            'amount' => '50.00',
        ]);

        OwnerAlert::factory()->create([
            'business_id' => $owner->business_id,
            'verification_id' => $verification->id,
            'to' => $owner->business->owner_whatsapp,
            'status' => 'delivered',
        ]);
        OwnerAlert::factory()->create();

        $response = $this->actingAs($owner)->getJson('/api/panel/owner-alerts');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.to', $owner->business->owner_whatsapp);
        $response->assertJsonPath('data.0.status', 'delivered');
        $response->assertJsonPath('data.0.template', 'alerta_pago_sospechoso');
        $response->assertJsonPath('data.0.verification.id', $verification->id);
        $response->assertJsonPath('data.0.verification.amount', '50.00');
        $response->assertJsonPath('data.0.verification.cashier_name', $cashier->name);
    }

    public function test_list_filters_by_dates_and_status(): void
    {
        $owner = User::factory()->create();

        OwnerAlert::factory()->create([
            'business_id' => $owner->business_id,
            'status' => 'sent',
            'created_at' => now()->subDays(5),
        ]);
        OwnerAlert::factory()->create([
            'business_id' => $owner->business_id,
            'status' => 'failed',
            'created_at' => now()->subDay(),
        ]);

        $from = now('America/Lima')->subDays(2)->toDateString();
        $to = now('America/Lima')->toDateString();

        $response = $this->actingAs($owner)->getJson("/api/panel/owner-alerts?date_from={$from}&date_to={$to}");

        $this->assertCount(1, $response->json('data'));

        $response = $this->actingAs($owner)->getJson('/api/panel/owner-alerts?status=sent');

        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.status', 'sent');

        $this->actingAs($owner)->getJson('/api/panel/owner-alerts?status=bogus')
            ->assertStatus(422);
    }

    public function test_list_filters_by_simulated_and_hides_trial_alerts(): void
    {
        $owner = User::factory()->create();
        $business = $owner->business;
        $cashier = Cashier::factory()->create(['business_id' => $business->id]);

        OwnerAlert::factory()->create([
            'business_id' => $business->id,
            'status' => 'simulated',
        ]);

        $trialVerification = Verification::factory()->for($business)->for($cashier)->create([
            'source' => 'trial',
            'trial_key' => (string) Str::uuid(),
        ]);
        OwnerAlert::factory()->create([
            'business_id' => $business->id,
            'verification_id' => $trialVerification->id,
            'status' => 'simulated',
        ]);

        $response = $this->actingAs($owner)->getJson('/api/panel/owner-alerts?status=simulated');

        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.status', 'simulated');
    }
}
