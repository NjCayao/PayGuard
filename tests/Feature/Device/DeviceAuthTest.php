<?php

namespace Tests\Feature\Device;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class DeviceAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_login_creates_device_and_returns_token(): void
    {
        $owner = User::factory()->create();

        $response = $this->postJson('/api/device/login', [
            'email' => $owner->email,
            'password' => 'password',
            'brand' => 'Xiaomi',
            'model' => 'Redmi 12',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.device.name', 'Xiaomi Redmi 12');
        $this->assertNotEmpty($response->json('data.token'));

        $device = Device::where('business_id', $owner->business_id)->firstOrFail();
        $this->assertSame('Xiaomi Redmi 12', $device->name);
        $this->assertNotNull($device->last_seen_at);

        $this->withToken($response->json('data.token'))
            ->postJson('/api/device/heartbeat')
            ->assertOk();
    }

    public function test_app_login_reuses_device_with_same_name(): void
    {
        $owner = User::factory()->create();
        $payload = [
            'email' => $owner->email,
            'password' => 'password',
            'brand' => 'Xiaomi',
            'model' => 'Redmi 12',
        ];

        $first = $this->postJson('/api/device/login', $payload)->assertOk();
        $this->postJson('/api/device/login', $payload)->assertOk();

        $this->assertSame(1, Device::where('business_id', $owner->business_id)->count());

        $this->withToken($first->json('data.token'))
            ->postJson('/api/device/heartbeat')
            ->assertUnauthorized();
    }

    public function test_app_login_rejects_bad_credentials(): void
    {
        $owner = User::factory()->create();

        $this->postJson('/api/device/login', [
            'email' => $owner->email,
            'password' => 'wrong-password',
            'brand' => 'Xiaomi',
            'model' => 'Redmi 12',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'El correo o la contraseña no son correctos.');

        $this->assertSame(0, Device::count());
    }

    public function test_heartbeat_updates_last_seen_at(): void
    {
        $owner = User::factory()->create();
        $device = Device::factory()->create([
            'business_id' => $owner->business_id,
            'last_seen_at' => now()->subDay(),
        ]);
        $token = $device->createToken('android-app')->plainTextToken;

        $this->withToken($token)->postJson('/api/device/heartbeat')->assertOk();

        $this->assertTrue($device->fresh()->last_seen_at->greaterThan(now()->subMinute()));
    }

    public function test_logout_revokes_token(): void
    {
        $owner = User::factory()->create();
        $device = Device::factory()->create(['business_id' => $owner->business_id]);
        $token = $device->createToken('android-app')->plainTextToken;

        $this->withToken($token)->postJson('/api/device/logout')->assertOk();

        $this->assertSame(0, $device->tokens()->count());

        Auth::forgetGuards();

        $this->withToken($token)->postJson('/api/device/heartbeat')->assertUnauthorized();
    }

    public function test_guest_cannot_use_device_api(): void
    {
        $this->postJson('/api/device/heartbeat')->assertUnauthorized();
        $this->postJson('/api/device/payments', [])->assertUnauthorized();
        $this->postJson('/api/device/logout')->assertUnauthorized();
    }
}
