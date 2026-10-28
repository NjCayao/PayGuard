<?php

namespace Tests\Feature\Panel;

use App\Models\Cashier;
use App\Models\Device;
use App\Models\OwnerAlert;
use App\Models\ReceivedPayment;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_counts_today_in_business_timezone(): void
    {
        $owner = User::factory()->create();
        $business = $owner->business;
        $cashier = Cashier::factory()->create(['business_id' => $business->id]);
        $device = Device::factory()->create(['business_id' => $business->id, 'last_seen_at' => now()]);

        ReceivedPayment::factory()->create([
            'business_id' => $business->id,
            'device_id' => $device->id,
            'amount' => '30.00',
            'received_at' => now(),
        ]);
        ReceivedPayment::factory()->create([
            'business_id' => $business->id,
            'device_id' => $device->id,
            'amount' => '20.00',
            'received_at' => now(),
        ]);
        ReceivedPayment::factory()->create([
            'business_id' => $business->id,
            'device_id' => $device->id,
            'amount' => '999.00',
            'received_at' => now()->subDay(),
        ]);

        foreach (['verified', 'pending', 'not_found', 'mismatch', 'reused'] as $status) {
            Verification::factory()->for($business)->for($cashier)->create([
                'status' => $status,
                'reason_code' => $status === 'pending' ? 'pending' : $status,
            ]);
        }
        Verification::factory()->for($business)->for($cashier)->create([
            'input_type' => 'text',
            'status' => 'error',
            'reason_code' => 'text_help',
        ]);
        Verification::factory()->for($business)->for($cashier)->create([
            'status' => 'verified',
            'created_at' => now()->subDay(),
        ]);

        OwnerAlert::factory()->create([
            'business_id' => $business->id,
            'status' => 'delivered',
        ]);
        OwnerAlert::factory()->create([
            'business_id' => $business->id,
            'status' => 'failed',
        ]);

        $response = $this->actingAs($owner)->getJson('/api/panel/summary');

        $response->assertOk();
        $response->assertJsonPath('data.received_payments.count', 2);
        $response->assertJsonPath('data.received_payments.total', '50.00');
        $response->assertJsonPath('data.verifications.verified', 1);
        $response->assertJsonPath('data.verifications.pending', 1);
        $response->assertJsonPath('data.verifications.failed', 2);
        $response->assertJsonPath('data.verifications.reused', 1);
        $response->assertJsonPath('data.owner_alerts_sent', 1);
        $response->assertJsonPath('data.device.online', true);
        $this->assertNotNull($response->json('data.device.last_seen_at'));
    }

    public function test_summary_reports_device_offline(): void
    {
        $owner = User::factory()->create();
        Device::factory()->create([
            'business_id' => $owner->business_id,
            'last_seen_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($owner)->getJson('/api/panel/summary');

        $response->assertOk();
        $response->assertJsonPath('data.device.online', false);
    }

    public function test_summary_is_scoped_to_own_business(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $device = Device::factory()->create(['business_id' => $other->business_id]);

        ReceivedPayment::factory()->create([
            'business_id' => $other->business_id,
            'device_id' => $device->id,
            'amount' => '100.00',
            'received_at' => now(),
        ]);

        $response = $this->actingAs($owner)->getJson('/api/panel/summary');

        $response->assertJsonPath('data.received_payments.count', 0);
        $response->assertJsonPath('data.verifications.verified', 0);
    }
}
