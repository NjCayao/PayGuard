<?php

namespace Tests\Feature\Panel;

use App\Models\Cashier;
use App\Models\OwnerAlert;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_lists_only_own_verifications_with_expected_fields(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $cashier = Cashier::factory()->create(['business_id' => $owner->business_id]);

        Verification::factory()->for($owner->business)->for($cashier)->create([
            'status' => 'verified',
            'reason_code' => 'verified',
            'amount' => '50.00',
            'recipient_name' => 'Maria Torres',
            'reason' => 'Yape recibido: S/ 50.00 de Juan Perez',
        ]);
        Verification::factory()->create(['business_id' => $other->business_id]);

        $response = $this->actingAs($owner)->getJson('/api/panel/verifications');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.amount', '50.00');
        $response->assertJsonPath('data.0.recipient_name', 'Maria Torres');
        $response->assertJsonPath('data.0.status', 'verified');
        $response->assertJsonPath('data.0.reason', 'Yape recibido: S/ 50.00 de Juan Perez');
        $response->assertJsonPath('data.0.cashier.name', $cashier->name);
    }

    public function test_list_filters_by_dates_cashier_and_status(): void
    {
        $owner = User::factory()->create();
        $business = $owner->business;
        $ana = Cashier::factory()->create(['business_id' => $business->id, 'name' => 'Ana']);
        $luis = Cashier::factory()->create(['business_id' => $business->id, 'name' => 'Luis']);

        Verification::factory()->for($business)->for($ana)->create([
            'status' => 'verified',
            'created_at' => now()->subDays(5),
        ]);
        Verification::factory()->for($business)->for($luis)->create([
            'status' => 'not_found',
            'reason_code' => 'not_found',
            'created_at' => now()->subDay(),
        ]);
        Verification::factory()->for($business)->for($ana)->create([
            'status' => 'not_found',
            'reason_code' => 'not_found',
            'created_at' => now()->subDay(),
        ]);

        $from = now('America/Lima')->subDays(2)->toDateString();
        $to = now('America/Lima')->toDateString();

        $response = $this->actingAs($owner)->getJson("/api/panel/verifications?date_from={$from}&date_to={$to}");

        $this->assertCount(2, $response->json('data'));

        $response = $this->actingAs($owner)->getJson("/api/panel/verifications?cashier_id={$ana->id}");

        $this->assertCount(2, $response->json('data'));

        $response = $this->actingAs($owner)->getJson('/api/panel/verifications?status=verified');

        $this->assertCount(1, $response->json('data'));

        $response = $this->actingAs($owner)->getJson("/api/panel/verifications?status=not_found&cashier_id={$luis->id}");

        $this->assertCount(1, $response->json('data'));
    }

    public function test_list_filters_by_input_type(): void
    {
        $owner = User::factory()->create();
        $cashier = Cashier::factory()->create(['business_id' => $owner->business_id]);

        Verification::factory()->for($owner->business)->for($cashier)->create([
            'input_type' => 'audio',
            'status' => 'verified',
            'reason_code' => 'verified',
        ]);
        Verification::factory()->for($owner->business)->for($cashier)->create([
            'input_type' => 'text',
            'status' => 'verified',
            'reason_code' => 'verified',
        ]);

        $response = $this->actingAs($owner)->getJson('/api/panel/verifications?input_type=audio');

        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.input_type', 'audio');
    }

    public function test_list_rejects_invalid_filters(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->getJson('/api/panel/verifications?status=bogus')
            ->assertStatus(422);

        $this->actingAs($owner)->getJson('/api/panel/verifications?input_type=bogus')
            ->assertStatus(422);

        $this->actingAs($owner)->getJson('/api/panel/verifications?date_from=2026-13-40')
            ->assertStatus(422);

        $this->actingAs($owner)->getJson('/api/panel/verifications?date_from=2026-10-10&date_to=2026-10-01')
            ->assertStatus(422);

        $foreign = Cashier::factory()->create();

        $this->actingAs($owner)->getJson("/api/panel/verifications?cashier_id={$foreign->id}")
            ->assertStatus(422);
    }

    public function test_list_excludes_text_help_replies(): void
    {
        $owner = User::factory()->create();
        $cashier = Cashier::factory()->create(['business_id' => $owner->business_id]);

        Verification::factory()->for($owner->business)->for($cashier)->create([
            'input_type' => 'text',
            'status' => 'error',
            'reason_code' => 'text_help',
        ]);
        Verification::factory()->for($owner->business)->for($cashier)->create([
            'input_type' => 'image',
            'status' => 'error',
            'reason_code' => 'error',
        ]);

        $response = $this->actingAs($owner)->getJson('/api/panel/verifications');

        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.reason_code', 'error');
    }

    public function test_show_includes_payment_and_alerts(): void
    {
        $owner = User::factory()->create();
        $cashier = Cashier::factory()->create(['business_id' => $owner->business_id]);

        $verification = Verification::factory()->for($owner->business)->for($cashier)->create([
            'status' => 'reused',
            'reason_code' => 'reused',
            'edit_signals' => ['Blurry digits'],
            'extracted' => ['wallet' => 'yape'],
        ]);
        OwnerAlert::factory()->create([
            'business_id' => $owner->business_id,
            'verification_id' => $verification->id,
            'status' => 'delivered',
        ]);

        $response = $this->actingAs($owner)->getJson("/api/panel/verifications/{$verification->id}");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'reused');
        $response->assertJsonPath('data.edit_signals', ['Blurry digits']);
        $this->assertCount(1, $response->json('data.alerts'));
        $response->assertJsonPath('data.alerts.0.status', 'delivered');
    }

    public function test_show_includes_query_fields_and_audio_url(): void
    {
        $owner = User::factory()->create();
        $cashier = Cashier::factory()->create(['business_id' => $owner->business_id]);

        $verification = Verification::factory()->for($owner->business)->for($cashier)->create([
            'input_type' => 'audio',
            'status' => 'verified',
            'reason_code' => 'verified',
            'media_path' => 'whatsapp/1/1.ogg',
            'transcript' => 'yape de cincuenta soles',
            'extracted' => ['query' => ['payer_name' => 'Juan']],
        ]);

        $response = $this->actingAs($owner)->getJson("/api/panel/verifications/{$verification->id}");

        $response->assertOk();
        $response->assertJsonPath('data.input_type', 'audio');
        $response->assertJsonPath('data.transcript', 'yape de cincuenta soles');
        $response->assertJsonPath('data.query_text', 'yape de cincuenta soles');
        $response->assertJsonPath('data.query_name', 'Juan');
        $response->assertJsonPath('data.has_audio', true);
        $response->assertJsonPath(
            'data.audio_url',
            route('panel.verifications.audio', $verification->id)
        );
        $response->assertJsonPath('data.has_image', false);
    }

    public function test_show_rejects_foreign_verification(): void
    {
        $owner = User::factory()->create();
        $foreign = Verification::factory()->create();

        $this->actingAs($owner)->getJson("/api/panel/verifications/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_image_serves_media_only_to_owner(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $cashier = Cashier::factory()->create(['business_id' => $owner->business_id]);

        $path = "whatsapp/{$owner->business_id}/1.jpg";
        Storage::disk('local')->put($path, 'fake-image-bytes');

        $verification = Verification::factory()->for($owner->business)->for($cashier)->create([
            'media_path' => $path,
        ]);
        $withoutMedia = Verification::factory()->for($owner->business)->for($cashier)->create([
            'media_path' => null,
        ]);

        $response = $this->actingAs($owner)->get("/api/panel/verifications/{$verification->id}/image");

        $response->assertOk();
        $this->assertSame(
            'fake-image-bytes',
            file_get_contents($response->baseResponse->getFile()->getPathname())
        );

        $this->actingAs($owner)->get("/api/panel/verifications/{$withoutMedia->id}/image")
            ->assertNotFound();

        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/api/panel/verifications/{$verification->id}/image")
            ->assertNotFound();
    }

    public function test_audio_serves_media_only_to_owner(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $cashier = Cashier::factory()->create(['business_id' => $owner->business_id]);

        $path = "whatsapp/{$owner->business_id}/1.ogg";
        Storage::disk('local')->put($path, 'fake-audio-bytes');

        $verification = Verification::factory()->for($owner->business)->for($cashier)->create([
            'input_type' => 'audio',
            'media_path' => $path,
        ]);
        $image = Verification::factory()->for($owner->business)->for($cashier)->create([
            'input_type' => 'image',
            'media_path' => $path,
        ]);

        $response = $this->actingAs($owner)->get("/api/panel/verifications/{$verification->id}/audio");

        $response->assertOk();
        $this->assertSame(
            'fake-audio-bytes',
            file_get_contents($response->baseResponse->getFile()->getPathname())
        );

        $this->actingAs($owner)->get("/api/panel/verifications/{$image->id}/audio")
            ->assertNotFound();

        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/api/panel/verifications/{$verification->id}/audio")
            ->assertNotFound();
    }
}
