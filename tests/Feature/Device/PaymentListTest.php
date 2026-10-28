<?php

namespace Tests\Feature\Device;

use App\Models\Device;
use App\Models\ReceivedPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentListTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $owner): string
    {
        $device = Device::factory()->create(['business_id' => $owner->business_id]);

        return $device->createToken('android-app')->plainTextToken;
    }

    public function test_app_lists_own_payments_newest_first(): void
    {
        $owner = User::factory()->create();
        $device = Device::factory()->create(['business_id' => $owner->business_id]);

        ReceivedPayment::factory()->create([
            'business_id' => $owner->business_id,
            'device_id' => $device->id,
            'received_at' => Carbon::parse('2026-10-27 10:00', 'America/Lima')->utc(),
        ]);
        $latest = ReceivedPayment::factory()->create([
            'business_id' => $owner->business_id,
            'device_id' => $device->id,
            'received_at' => Carbon::parse('2026-10-27 12:00', 'America/Lima')->utc(),
        ]);

        $response = $this->withToken($this->tokenFor($owner))
            ->getJson('/api/device/payments');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $response->assertJsonPath('data.0.id', $latest->id);
    }

    public function test_app_cannot_list_other_business_payments(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $foreign = ReceivedPayment::factory()->create([
            'business_id' => $other->business_id,
            'device_id' => Device::factory()->create(['business_id' => $other->business_id])->id,
        ]);

        $response = $this->withToken($this->tokenFor($owner))
            ->getJson('/api/device/payments');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
        $response->assertJsonMissing(['id' => $foreign->id]);
    }

    public function test_app_list_respects_limit(): void
    {
        $owner = User::factory()->create();
        $device = Device::factory()->create(['business_id' => $owner->business_id]);
        ReceivedPayment::factory()->count(3)->create([
            'business_id' => $owner->business_id,
            'device_id' => $device->id,
        ]);

        $response = $this->withToken($this->tokenFor($owner))
            ->getJson('/api/device/payments?limit=2');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_app_list_rejects_limit_over_fifty(): void
    {
        $owner = User::factory()->create();

        $this->withToken($this->tokenFor($owner))
            ->getJson('/api/device/payments?limit=51')
            ->assertUnprocessable();
    }

    public function test_app_list_reports_today_count_in_business_timezone(): void
    {
        $owner = User::factory()->create();
        $device = Device::factory()->create(['business_id' => $owner->business_id]);

        ReceivedPayment::factory()->create([
            'business_id' => $owner->business_id,
            'device_id' => $device->id,
            'received_at' => Carbon::now('America/Lima')->utc(),
        ]);
        ReceivedPayment::factory()->create([
            'business_id' => $owner->business_id,
            'device_id' => $device->id,
            'received_at' => Carbon::now('America/Lima')->subDay()->utc(),
        ]);

        $response = $this->withToken($this->tokenFor($owner))
            ->getJson('/api/device/payments');

        $response->assertOk();
        $response->assertJsonPath('meta.today_count', 1);
    }

    public function test_guest_cannot_list_payments(): void
    {
        $this->getJson('/api/device/payments')->assertUnauthorized();
    }
}
