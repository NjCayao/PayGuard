<?php

namespace Tests\Feature\Panel;

use App\Models\Device;
use App\Models\ReceivedPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivedPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function deviceFor(User $owner): Device
    {
        return Device::factory()->create(['business_id' => $owner->business_id]);
    }

    public function test_owner_lists_only_own_payments(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $device = $this->deviceFor($owner);

        ReceivedPayment::factory()->count(2)->create([
            'business_id' => $owner->business_id,
            'device_id' => $device->id,
        ]);
        $foreign = ReceivedPayment::factory()->create([
            'business_id' => $other->business_id,
            'device_id' => $this->deviceFor($other)->id,
        ]);

        $response = $this->actingAs($owner)->getJson('/api/panel/received-payments');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $response->assertJsonMissing(['idempotency_key' => $foreign->idempotency_key]);
    }

    public function test_payment_list_shows_expected_fields(): void
    {
        $owner = User::factory()->create();
        $device = $this->deviceFor($owner);
        ReceivedPayment::factory()->create([
            'business_id' => $owner->business_id,
            'device_id' => $device->id,
            'amount' => '50.00',
            'payer_name' => 'JUAN PEREZ',
            'security_code' => '123',
            'parsed' => false,
        ]);

        $response = $this->actingAs($owner)->getJson('/api/panel/received-payments');

        $response->assertOk();
        $response->assertJsonPath('data.0.amount', '50.00');
        $response->assertJsonPath('data.0.payer_name', 'JUAN PEREZ');
        $response->assertJsonPath('data.0.security_code', '123');
        $response->assertJsonPath('data.0.parsed', false);
        $response->assertJsonPath('data.0.device.name', $device->name);
    }

    public function test_payment_list_can_filter_by_date_in_business_timezone(): void
    {
        $owner = User::factory()->create();
        $device = $this->deviceFor($owner);

        ReceivedPayment::factory()->create([
            'business_id' => $owner->business_id,
            'device_id' => $device->id,
            'received_at' => Carbon::parse('2026-10-27 10:00', 'America/Lima')->utc(),
        ]);
        ReceivedPayment::factory()->create([
            'business_id' => $owner->business_id,
            'device_id' => $device->id,
            'received_at' => Carbon::parse('2026-10-26 22:00', 'America/Lima')->utc(),
        ]);

        $response = $this->actingAs($owner)
            ->getJson('/api/panel/received-payments?date=2026-10-27');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_payment_list_is_paginated(): void
    {
        $owner = User::factory()->create();
        $device = $this->deviceFor($owner);
        ReceivedPayment::factory()->count(21)->create([
            'business_id' => $owner->business_id,
            'device_id' => $device->id,
        ]);

        $response = $this->actingAs($owner)->getJson('/api/panel/received-payments');

        $response->assertOk();
        $this->assertCount(20, $response->json('data'));
        $response->assertJsonPath('meta.total', 21);
    }

    public function test_guest_cannot_list_payments(): void
    {
        $this->getJson('/api/panel/received-payments')->assertUnauthorized();
    }
}
