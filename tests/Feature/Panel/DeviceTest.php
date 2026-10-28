<?php

namespace Tests\Feature\Panel;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_lists_only_own_devices(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Device::factory()->create(['business_id' => $owner->business_id]);
        $foreign = Device::factory()->create(['business_id' => $other->business_id]);

        $response = $this->actingAs($owner)->getJson('/api/panel/devices');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonMissing(['name' => $foreign->name]);
    }

    public function test_device_shows_online_status(): void
    {
        $owner = User::factory()->create();
        Device::factory()->create([
            'business_id' => $owner->business_id,
            'name' => 'Recent phone',
            'last_seen_at' => now()->subMinutes(5),
            'last_payment_at' => now()->subMinutes(4),
        ]);
        Device::factory()->create([
            'business_id' => $owner->business_id,
            'name' => 'Old phone',
            'last_seen_at' => now()->subMinutes(20),
        ]);
        Device::factory()->create([
            'business_id' => $owner->business_id,
            'name' => 'Silent phone',
            'last_seen_at' => null,
        ]);

        $response = $this->actingAs($owner)->getJson('/api/panel/devices');

        $response->assertOk();
        $byName = collect($response->json('data'))->keyBy('name');
        $this->assertTrue($byName['Recent phone']['is_online']);
        $this->assertFalse($byName['Old phone']['is_online']);
        $this->assertFalse($byName['Silent phone']['is_online']);
        $this->assertNotNull($byName['Recent phone']['last_payment_at']);
        $this->assertNull($byName['Old phone']['last_payment_at']);
    }

    public function test_owner_can_unlink_device(): void
    {
        $owner = User::factory()->create();
        $device = Device::factory()->create(['business_id' => $owner->business_id]);
        $token = $device->createToken('android-app')->plainTextToken;

        $this->actingAs($owner)
            ->postJson("/api/panel/devices/{$device->id}/unlink")
            ->assertOk();

        $this->assertSame(0, $device->tokens()->count());

        Auth::forgetGuards();

        $this->withToken($token)->postJson('/api/device/heartbeat')->assertUnauthorized();
    }

    public function test_owner_cannot_unlink_other_business_device(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $foreign = Device::factory()->create(['business_id' => $other->business_id]);
        $foreign->createToken('android-app');

        $this->actingAs($owner)
            ->postJson("/api/panel/devices/{$foreign->id}/unlink")
            ->assertNotFound();

        $this->assertSame(1, $foreign->tokens()->count());
    }

    public function test_guest_cannot_access_devices(): void
    {
        $this->getJson('/api/panel/devices')->assertUnauthorized();
    }
}
