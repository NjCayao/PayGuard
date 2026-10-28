<?php

namespace Tests\Feature\Panel;

use App\Models\Cashier;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_only_own_cashiers(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Cashier::factory()->count(2)->create(['business_id' => $owner->business_id]);
        $foreign = Cashier::factory()->create(['business_id' => $other->business_id]);

        $response = $this->actingAs($owner)->getJson('/api/panel/cashiers');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $response->assertJsonMissing(['whatsapp' => $foreign->whatsapp]);
    }

    public function test_cashier_list_is_paginated(): void
    {
        $owner = User::factory()->create();
        Cashier::factory()->count(21)->create(['business_id' => $owner->business_id]);

        $response = $this->actingAs($owner)->getJson('/api/panel/cashiers');

        $response->assertOk();
        $this->assertCount(20, $response->json('data'));
        $response->assertJsonPath('meta.total', 21);
    }

    public function test_owner_can_create_cashier_with_normalized_whatsapp(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->postJson('/api/panel/cashiers', [
            'name' => 'Juan Perez',
            'whatsapp' => '+51 999 888 777',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.whatsapp', '51999888777');

        $this->assertDatabaseHas('cashiers', [
            'business_id' => $owner->business_id,
            'name' => 'Juan Perez',
            'whatsapp' => '51999888777',
            'is_active' => true,
        ]);
    }

    public function test_cashier_whatsapp_is_unique_across_businesses(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Cashier::factory()->create([
            'business_id' => $other->business_id,
            'whatsapp' => '51999888777',
        ]);

        $response = $this->actingAs($owner)->postJson('/api/panel/cashiers', [
            'name' => 'Juan Perez',
            'whatsapp' => '51999888777',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('whatsapp');
    }

    public function test_whatsapp_normalization_prevents_duplicates(): void
    {
        $owner = User::factory()->create();
        Cashier::factory()->create([
            'business_id' => $owner->business_id,
            'whatsapp' => '51999888777',
        ]);

        $response = $this->actingAs($owner)->postJson('/api/panel/cashiers', [
            'name' => 'Juan Perez',
            'whatsapp' => '+51 999-888 777',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('whatsapp');
    }

    public function test_owner_can_update_cashier(): void
    {
        $owner = User::factory()->create();
        $cashier = Cashier::factory()->create(['business_id' => $owner->business_id]);

        $response = $this->actingAs($owner)->putJson("/api/panel/cashiers/{$cashier->id}", [
            'name' => 'Juan Paredes',
            'whatsapp' => '+51 999 888 777',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('cashiers', [
            'id' => $cashier->id,
            'name' => 'Juan Paredes',
            'whatsapp' => '51999888777',
        ]);
    }

    public function test_owner_can_activate_and_deactivate_cashier(): void
    {
        $owner = User::factory()->create();
        $cashier = Cashier::factory()->create([
            'business_id' => $owner->business_id,
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->patchJson("/api/panel/cashiers/{$cashier->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($owner)
            ->patchJson("/api/panel/cashiers/{$cashier->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_owner_cannot_touch_other_business_cashier(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $foreign = Cashier::factory()->create(['business_id' => $other->business_id]);

        $this->actingAs($owner)->putJson("/api/panel/cashiers/{$foreign->id}", [
            'name' => 'Hacker',
            'whatsapp' => '51911111111',
        ])->assertNotFound();

        $this->actingAs($owner)
            ->patchJson("/api/panel/cashiers/{$foreign->id}/activate")
            ->assertNotFound();

        $this->actingAs($owner)
            ->deleteJson("/api/panel/cashiers/{$foreign->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('cashiers', ['id' => $foreign->id]);
    }

    public function test_owner_can_delete_cashier_without_verifications(): void
    {
        $owner = User::factory()->create();
        $cashier = Cashier::factory()->create(['business_id' => $owner->business_id]);

        $this->actingAs($owner)
            ->deleteJson("/api/panel/cashiers/{$cashier->id}")
            ->assertOk();

        $this->assertDatabaseMissing('cashiers', ['id' => $cashier->id]);
    }

    public function test_owner_cannot_delete_cashier_with_verifications(): void
    {
        $owner = User::factory()->create();
        $cashier = Cashier::factory()->create(['business_id' => $owner->business_id]);
        Verification::create([
            'business_id' => $owner->business_id,
            'cashier_id' => $cashier->id,
            'wa_message_id' => 'wamid.test.1',
            'input_type' => 'image',
            'status' => 'verified',
        ]);

        $response = $this->actingAs($owner)
            ->deleteJson("/api/panel/cashiers/{$cashier->id}");

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'No se puede eliminar un cajero con verificaciones registradas.');
        $this->assertDatabaseHas('cashiers', ['id' => $cashier->id]);
    }

    public function test_guest_cannot_access_cashiers(): void
    {
        $this->getJson('/api/panel/cashiers')->assertUnauthorized();
    }
}
