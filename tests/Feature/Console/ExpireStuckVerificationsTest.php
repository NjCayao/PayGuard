<?php

namespace Tests\Feature\Console;

use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExpireStuckVerificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payguard.whatsapp.token', 'test-token');
        config()->set('payguard.whatsapp.phone_number_id', '12345');
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
        ]);
    }

    public function test_closes_old_processing_images_in_silence(): void
    {
        $verification = Verification::factory()->create([
            'input_type' => 'image',
            'status' => 'processing',
        ]);
        Verification::whereKey($verification->id)->update([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->artisan('verifications:expire-stuck')->assertSuccessful();

        $verification->refresh();

        $this->assertSame('error', $verification->status);
        $this->assertSame('La revisión tardó demasiado. Vuelve a enviar la captura.', $verification->reason);
        Http::assertNothingSent();
    }

    public function test_notifies_recent_messages(): void
    {
        $verification = Verification::factory()->create([
            'input_type' => 'image',
            'status' => 'processing',
        ]);
        Verification::whereKey($verification->id)->update([
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $this->artisan('verifications:expire-stuck')->assertSuccessful();

        $this->assertSame('error', $verification->fresh()->status);

        Http::assertSent(function ($request) use ($verification) {
            $data = $request->data();

            return ($data['type'] ?? '') === 'text'
                && ($data['context']['message_id'] ?? '') === $verification->wa_message_id
                && $data['text']['body'] === '❌ La revisión tardó demasiado. Vuelve a enviar la captura.';
        });
    }

    public function test_closes_old_processing_queries_with_their_own_text(): void
    {
        $audio = Verification::factory()->create([
            'input_type' => 'audio',
            'status' => 'processing',
        ]);
        Verification::whereKey($audio->id)->update([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->artisan('verifications:expire-stuck')->assertSuccessful();

        $audio->refresh();

        $this->assertSame('error', $audio->status);
        $this->assertSame('expired', $audio->reason_code);
        $this->assertSame('La revisión tardó demasiado. Vuelve a preguntar.', $audio->reason);
    }

    public function test_keeps_recent_processing_and_pending_rows(): void
    {
        $recent = Verification::factory()->create(['input_type' => 'image', 'status' => 'processing']);
        $pending = Verification::factory()->create(['input_type' => 'image', 'status' => 'pending']);

        Verification::whereKey($pending->id)->update(['updated_at' => now()->subHour()]);

        $this->artisan('verifications:expire-stuck')->assertSuccessful();

        $this->assertSame('processing', $recent->fresh()->status);
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $verification = Verification::factory()->create([
            'input_type' => 'image',
            'status' => 'processing',
        ]);
        Verification::whereKey($verification->id)->update(['updated_at' => now()->subHour()]);

        $this->artisan('verifications:expire-stuck', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('processing', $verification->fresh()->status);
        Http::assertNothingSent();
    }
}
