<?php

namespace Tests\Feature\Demo;

use App\Models\Business;
use App\Models\Cashier;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_cannot_create_update_or_delete_cashiers(): void
    {
        $owner = $this->demoOwner();
        $cashier = Cashier::factory()->create(['business_id' => $owner->business_id]);
        $message = (string) config('payguard.demo.blocked_message');

        $this->actingAs($owner)->postJson('/api/panel/cashiers', [
            'name' => 'Intruso',
            'whatsapp' => '51911111111',
        ])->assertForbidden()->assertJsonPath('message', $message);

        $this->actingAs($owner)->putJson("/api/panel/cashiers/{$cashier->id}", [
            'name' => 'Cambiado',
            'whatsapp' => $cashier->whatsapp,
        ])->assertForbidden()->assertJsonPath('message', $message);

        $this->actingAs($owner)
            ->patchJson("/api/panel/cashiers/{$cashier->id}/deactivate")
            ->assertForbidden()->assertJsonPath('message', $message);

        $this->actingAs($owner)
            ->patchJson("/api/panel/cashiers/{$cashier->id}/activate")
            ->assertForbidden()->assertJsonPath('message', $message);

        $this->actingAs($owner)
            ->deleteJson("/api/panel/cashiers/{$cashier->id}")
            ->assertForbidden()->assertJsonPath('message', $message);

        $this->assertDatabaseHas('cashiers', [
            'id' => $cashier->id,
            'name' => $cashier->name,
            'is_active' => true,
        ]);
    }

    public function test_demo_cannot_unlink_devices(): void
    {
        $owner = $this->demoOwner();
        $device = Device::factory()->create(['business_id' => $owner->business_id]);
        $device->createToken('app');

        $this->actingAs($owner)
            ->postJson("/api/panel/devices/{$device->id}/unlink")
            ->assertForbidden()
            ->assertJsonPath('message', (string) config('payguard.demo.blocked_message'));

        $this->assertSame(1, $device->fresh()->tokens()->count());
    }

    public function test_demo_cannot_update_settings(): void
    {
        $owner = $this->demoOwner();

        $this->actingAs($owner)->putJson('/api/panel/settings', [
            'name' => 'Cambiado',
            'match_window_minutes' => 15,
            'recheck_minutes' => 3,
        ])->assertForbidden()
            ->assertJsonPath('message', (string) config('payguard.demo.blocked_message'));

        $this->assertNotSame('Cambiado', $owner->business->fresh()->name);
    }

    public function test_demo_can_still_read_the_panel(): void
    {
        $owner = $this->demoOwner();

        $this->actingAs($owner)->getJson('/api/panel/account')->assertOk();
        $this->actingAs($owner)->getJson('/api/panel/settings')->assertOk();
        $this->actingAs($owner)->getJson('/api/panel/cashiers')->assertOk();
        $this->actingAs($owner)->getJson('/api/panel/devices')->assertOk();
        $this->actingAs($owner)->getJson('/api/panel/user')
            ->assertOk()
            ->assertJsonPath('data.business.is_demo', true);
    }

    public function test_real_business_is_not_blocked(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->postJson('/api/panel/cashiers', [
            'name' => 'Juan Perez',
            'whatsapp' => '51911111111',
        ])->assertCreated();

        $this->actingAs($owner)->putJson('/api/panel/settings', [
            'name' => 'Mi Bodega',
            'match_window_minutes' => 15,
            'recheck_minutes' => 3,
        ])->assertOk();
    }

    private function demoOwner(): User
    {
        $business = Business::factory()->create(['is_demo' => true]);

        return User::factory()->for($business)->create();
    }
}
