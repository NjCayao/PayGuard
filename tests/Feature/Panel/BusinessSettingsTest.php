<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_views_settings(): void
    {
        $owner = User::factory()->create();
        $owner->business->update(['yape_holder_name' => 'Maria Torres']);

        $response = $this->actingAs($owner)->getJson('/api/panel/settings');

        $response->assertOk();
        $response->assertJsonPath('data.business.yape_holder_name', 'Maria Torres');
        $response->assertJsonPath('data.business.match_window_minutes', 10);
        $response->assertJsonPath('data.business.recheck_minutes', 2);
    }

    public function test_owner_updates_settings(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->putJson('/api/panel/settings', [
            'name' => 'Bodega El Sol',
            'yape_holder_name' => 'Maria Torres Quispe',
            'match_window_minutes' => 15,
            'recheck_minutes' => 3,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.business.name', 'Bodega El Sol');

        $this->assertDatabaseHas('businesses', [
            'id' => $owner->business_id,
            'yape_holder_name' => 'Maria Torres Quispe',
            'match_window_minutes' => 15,
            'recheck_minutes' => 3,
        ]);
    }

    public function test_update_ignores_owner_whatsapp(): void
    {
        $owner = User::factory()->create();
        $before = $owner->business->owner_whatsapp;

        $this->actingAs($owner)->putJson('/api/panel/settings', [
            'name' => 'Bodega El Sol',
            'owner_whatsapp' => '51911111111',
            'match_window_minutes' => 15,
            'recheck_minutes' => 3,
        ])->assertOk();

        $this->assertSame($before, $owner->business->fresh()->owner_whatsapp);
    }

    public function test_update_rejects_invalid_values(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->putJson('/api/panel/settings', [
            'name' => 'Bodega El Sol',
            'match_window_minutes' => 99,
            'recheck_minutes' => 3,
        ])->assertStatus(422);

        $this->actingAs($owner)->putJson('/api/panel/settings', [
            'name' => 'Bodega El Sol',
            'match_window_minutes' => 15,
            'recheck_minutes' => 99,
        ])->assertStatus(422);
    }

    public function test_update_errors_come_back_in_spanish(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->putJson('/api/panel/settings', [
            'name' => 'Bodega El Sol',
            'yape_holder_name' => str_repeat('a', 121),
            'match_window_minutes' => 'many',
            'recheck_minutes' => 3,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('yape_holder_name');
        $response->assertJsonValidationErrors('match_window_minutes');
        $response->assertJsonPath('errors.yape_holder_name.0', 'El titular no puede pasar de 120 caracteres.');
        $response->assertJsonPath('errors.match_window_minutes.0', 'La ventana debe ser un número de minutos.');
    }

    public function test_owner_cannot_update_other_business(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->putJson('/api/panel/settings', [
            'name' => 'Hacked',
            'match_window_minutes' => 15,
            'recheck_minutes' => 3,
        ])->assertOk();

        $this->assertNotSame('Hacked', $other->business->fresh()->name);
    }
}
