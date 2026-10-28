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
            'owner_whatsapp' => '+51 982 226 835',
            'match_window_minutes' => 15,
            'recheck_minutes' => 3,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.business.name', 'Bodega El Sol');
        $response->assertJsonPath('data.business.owner_whatsapp', '51982226835');

        $this->assertDatabaseHas('businesses', [
            'id' => $owner->business_id,
            'yape_holder_name' => 'Maria Torres Quispe',
            'match_window_minutes' => 15,
            'recheck_minutes' => 3,
        ]);
    }

    public function test_update_rejects_invalid_values(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->putJson('/api/panel/settings', [
            'name' => 'Bodega El Sol',
            'owner_whatsapp' => 'not-a-number',
            'match_window_minutes' => 15,
            'recheck_minutes' => 3,
        ])->assertStatus(422);

        $this->actingAs($owner)->putJson('/api/panel/settings', [
            'name' => 'Bodega El Sol',
            'owner_whatsapp' => '51982226835',
            'match_window_minutes' => 99,
            'recheck_minutes' => 3,
        ])->assertStatus(422);

        $this->actingAs($owner)->putJson('/api/panel/settings', [
            'name' => 'Bodega El Sol',
            'owner_whatsapp' => '51982226835',
            'match_window_minutes' => 15,
            'recheck_minutes' => 99,
        ])->assertStatus(422);
    }

    public function test_owner_cannot_update_other_business(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->putJson('/api/panel/settings', [
            'name' => 'Hacked',
            'owner_whatsapp' => '51982226835',
            'match_window_minutes' => 15,
            'recheck_minutes' => 3,
        ])->assertOk();

        $this->assertNotSame('Hacked', $other->business->fresh()->name);
    }
}
