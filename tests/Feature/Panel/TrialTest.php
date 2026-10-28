<?php

namespace Tests\Feature\Panel;

use App\Models\Business;
use App\Models\Cashier;
use App\Models\Device;
use App\Models\OwnerAlert;
use App\Models\ReceivedPayment;
use App\Models\User;
use App\Models\Verification;
use App\Services\Demo\DemoReceiptImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrialTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Business $business;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payguard.meta.base_url', 'https://api.meta.ai/v1');
        config()->set('payguard.meta.api_key', 'test-key');
        Storage::fake('local');

        $this->business = Business::factory()->create([
            'is_demo' => true,
            'name' => 'Bodega El Sol',
            'yape_holder_name' => 'Luz Marina Torres',
        ]);
        $this->owner = User::factory()->create(['business_id' => $this->business->id]);
        Cashier::factory()->for($this->business)->create(['name' => 'Rosa']);
        Device::factory()->for($this->business)->create();
        $this->key = (string) Str::uuid();
    }

    public function test_non_demo_accounts_are_forbidden(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->getJson('/api/panel/trial/session', $this->headers())
            ->assertForbidden();

        $this->actingAs($owner)->post('/api/panel/trial/image', [
            'image' => UploadedFile::fake()->createWithContent('receipt.png', $this->png()),
        ], $this->headers())->assertForbidden();

        $this->actingAs($owner)->postJson('/api/panel/trial/query', ['text' => 'yape de 50'], $this->headers())
            ->assertForbidden();

        $this->actingAs($owner)->get('/api/panel/trial/samples/valid', $this->headers())
            ->assertForbidden();
    }

    public function test_trial_key_is_required_but_accepts_query_param(): void
    {
        $this->actingAs($this->owner)->getJson('/api/panel/trial/session')
            ->assertStatus(422);

        $this->actingAs($this->owner)->getJson('/api/panel/trial/session', ['X-Trial-Key' => 'not-a-uuid'])
            ->assertStatus(422);

        $this->actingAs($this->owner)->getJson("/api/panel/trial/session?trial_key={$this->key}")
            ->assertOk()
            ->assertJsonPath('data.trial_key', $this->key);
    }

    public function test_session_seeds_trial_payments_once_per_key(): void
    {
        $first = $this->actingAs($this->owner)->getJson('/api/panel/trial/session', $this->headers());

        $first->assertOk();
        $this->assertCount(3, $first->json('data.payments'));
        $this->assertSame(
            ['50.00', '20.00', '35.50'],
            array_column($first->json('data.payments'), 'amount')
        );
        $this->assertSame(20, $first->json('data.limits.per_hour'));

        $this->actingAs($this->owner)->getJson('/api/panel/trial/session', $this->headers())->assertOk();

        $this->assertSame(3, ReceivedPayment::forBusiness($this->business->id)->count());

        $this->actingAs($this->owner)->getJson('/api/panel/trial/session', $this->headers((string) Str::uuid()))
            ->assertOk();

        $this->assertSame(6, ReceivedPayment::forBusiness($this->business->id)->count());
    }

    public function test_valid_image_is_verified_with_steps(): void
    {
        $this->fakeScreenshot();

        $response = $this->upload();

        $response->assertOk();
        $response->assertJsonPath('data.status', 'verified');
        $response->assertJsonPath('data.reason_code', 'verified');
        $response->assertJsonPath('data.ai.amount', '50.00');
        $response->assertJsonPath('data.ai.recipient_name', 'Luz Marina Torres');
        $response->assertJsonPath('data.ai.security_code', '123');
        $response->assertJsonPath('data.ai.edit_signals', []);
        $response->assertJsonPath('data.ai.wallet', 'yape');
        $this->assertIsInt($response->json('data.ai.ai_ms'));
        $response->assertJsonPath('data.match.searched.amount', '50.00');
        $response->assertJsonPath('data.match.searched.window_minutes', 10);
        $response->assertJsonPath('data.match.found.payer_name', 'Juan Pérez');
        $response->assertJsonPath('data.match.found.amount', '50.00');
        $response->assertJsonPath('data.reply', '✅ Yape recibido: S/ 50.00 de Juan Pérez · Cód. 123.');
        $response->assertJsonPath('data.alert_simulated', false);

        $verification = Verification::forBusiness($this->business->id)->sole();

        $this->assertTrue($verification->isTrial());
        $this->assertSame($this->key, $verification->trial_key);
        $this->assertNotNull($verification->received_payment_id);
        $this->assertSame(0, OwnerAlert::count());

        Http::assertSentCount(1);
    }

    public function test_same_image_twice_is_reused(): void
    {
        $this->fakeScreenshot();

        $bytes = $this->png();

        $this->upload(bytes: $bytes)->assertOk()->assertJsonPath('data.status', 'verified');

        $response = $this->upload(bytes: $bytes);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'reused');
        $response->assertJsonPath('data.reason_code', 'reused');
        $response->assertJsonPath('data.reply', '❌ Esta captura ya se usó antes.');
        $response->assertJsonPath('data.alert_simulated', true);

        $alert = OwnerAlert::forBusiness($this->business->id)->sole();

        $this->assertSame('simulated', $alert->status);
        $this->assertNull($alert->error);
    }

    public function test_edited_fake_image_is_mismatch_with_simulated_alert(): void
    {
        $this->fakeScreenshot(['amount' => '500.00']);

        $response = $this->upload();

        $response->assertOk();
        $response->assertJsonPath('data.status', 'mismatch');
        $response->assertJsonPath('data.reason_code', 'mismatch_amount');
        $this->assertStringContainsString('no de S/ 500.00', $response->json('data.reply'));
        $response->assertJsonPath('data.alert_simulated', true);

        $this->assertSame('simulated', OwnerAlert::forBusiness($this->business->id)->sole()->status);

        $list = $this->actingAs($this->owner)->getJson('/api/panel/owner-alerts');

        $this->assertCount(0, $list->json('data'));
    }

    public function test_text_query_is_verified(): void
    {
        $this->fakeQuery(['amount' => '20.00', 'payer_name' => 'Luz']);

        $response = $this->actingAs($this->owner)->postJson(
            '/api/panel/trial/query',
            ['text' => '¿llegó un yape de 20 de Luz?'],
            $this->headers()
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', 'verified');
        $response->assertJsonPath('data.ai.amount', '20.00');
        $response->assertJsonPath('data.ai.payer_name', 'Luz');
        $response->assertJsonPath('data.match.found.payer_name', 'Luz Quispe');
        $response->assertJsonPath('data.reply', '✅ Yape recibido: S/ 20.00 de Luz Quispe · Cód. 456.');
    }

    public function test_greeting_returns_help_without_ai_call(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        $response = $this->actingAs($this->owner)->postJson(
            '/api/panel/trial/query',
            ['text' => 'hola, gracias'],
            $this->headers()
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', 'error');
        $response->assertJsonPath('data.reason_code', 'text_help');
        $response->assertJsonPath('data.match', null);
        $response->assertJsonPath('data.reply', config('payguard.whatsapp.replies.text_help'));

        Http::assertNothingSent();
    }

    public function test_two_keys_are_isolated(): void
    {
        $this->fakeScreenshot();

        $this->upload()->assertJsonPath('data.status', 'verified');
        $this->upload(key: (string) Str::uuid())->assertJsonPath('data.status', 'verified');
    }

    public function test_trials_are_excluded_from_panel_lists(): void
    {
        $this->fakeScreenshot();
        $this->upload()->assertJsonPath('data.status', 'verified');

        $verification = Verification::forBusiness($this->business->id)->sole();

        $this->assertCount(0, $this->actingAs($this->owner)->getJson('/api/panel/verifications')->json('data'));
        $this->assertCount(0, $this->actingAs($this->owner)->getJson('/api/panel/received-payments')->json('data'));

        $summary = $this->actingAs($this->owner)->getJson('/api/panel/summary');

        $summary->assertJsonPath('data.verifications.verified', 0);
        $summary->assertJsonPath('data.received_payments.count', 0);

        $this->actingAs($this->owner)->getJson("/api/panel/verifications/{$verification->id}")->assertNotFound();
        $this->actingAs($this->owner)->get("/api/panel/verifications/{$verification->id}/image")->assertNotFound();
    }

    public function test_rate_limit_blocks_after_per_hour_quota(): void
    {
        config()->set('payguard.demo.trial.per_hour', 2);
        $this->fakeQuery();

        $headers = $this->headers();

        $this->actingAs($this->owner)->postJson('/api/panel/trial/query', ['text' => 'yape de 50'], $headers)
            ->assertOk();
        $this->actingAs($this->owner)->postJson('/api/panel/trial/query', ['text' => 'yape de 50'], $headers)
            ->assertOk();
        $this->actingAs($this->owner)->postJson('/api/panel/trial/query', ['text' => 'yape de 50'], $headers)
            ->assertStatus(429);
    }

    public function test_image_validation_rejects_non_images_and_oversize(): void
    {
        $this->actingAs($this->owner)->post('/api/panel/trial/image', [
            'image' => UploadedFile::fake()->create('note.txt', 10),
        ], $this->headers())->assertStatus(422);

        $this->actingAs($this->owner)->post('/api/panel/trial/image', [
            'image' => UploadedFile::fake()->image('big.png')->size(6000),
        ], $this->headers())->assertStatus(422);
    }

    public function test_samples_download_and_valid_refreshes_payment(): void
    {
        $this->actingAs($this->owner)->getJson('/api/panel/trial/session', $this->headers())->assertOk();

        $payment = ReceivedPayment::forBusiness($this->business->id)
            ->where('trial_key', $this->key)
            ->orderBy('id')
            ->first();
        $payment->update(['received_at' => now()->subMinutes(30)]);

        $valid = $this->actingAs($this->owner)->get("/api/panel/trial/samples/valid?trial_key={$this->key}");

        $valid->assertOk();
        $this->assertSame('image/png', $valid->headers->get('Content-Type'));
        $this->assertStringContainsString('muestra-valida.png', (string) $valid->headers->get('Content-Disposition'));
        $this->assertSame("\x89PNG\r\n\x1a\n", substr((string) $valid->baseResponse->getContent(), 0, 8));
        $this->assertTrue($payment->refresh()->received_at->greaterThan(now()->subMinute()));

        $fake = $this->actingAs($this->owner)->get("/api/panel/trial/samples/fake?trial_key={$this->key}");

        $fake->assertOk();
        $this->assertStringContainsString('muestra-falsa.png', (string) $fake->headers->get('Content-Disposition'));

        $this->actingAs($this->owner)->get("/api/panel/trial/samples/other?trial_key={$this->key}")
            ->assertNotFound();
    }

    public function test_model_failure_returns_error_result(): void
    {
        Http::fake(['api.meta.ai/*' => Http::response(['error' => 'boom'], 500)]);
        Http::preventStrayRequests();

        $response = $this->upload();

        $response->assertOk();
        $response->assertJsonPath('data.status', 'error');
        $response->assertJsonPath('data.reason_code', 'error');
        $response->assertJsonPath('data.reply', '❌ No se pudo leer la captura.');
    }

    private function headers(?string $key = null): array
    {
        return ['X-Trial-Key' => $key ?? $this->key];
    }

    private function upload(?string $key = null, ?string $bytes = null)
    {
        return $this->actingAs($this->owner)->post('/api/panel/trial/image', [
            'image' => UploadedFile::fake()->createWithContent('receipt.png', $bytes ?? $this->png()),
        ], $this->headers($key));
    }

    private function fakeScreenshot(array $overrides = []): void
    {
        $today = now('America/Lima');

        $this->fakeModel(array_merge([
            'wallet' => 'yape',
            'amount' => '50.00',
            'recipient_name' => 'Luz Marina Torres',
            'paid_date' => $today->toDateString(),
            'paid_time' => $today->format('H:i'),
            'security_code' => '123',
            'operation_code' => null,
            'edit_signals' => [],
        ], $overrides));
    }

    private function fakeQuery(array $overrides = []): void
    {
        $this->fakeModel(array_merge([
            'is_payment_query' => true,
            'wallet' => 'yape',
            'amount' => '50.00',
            'payer_name' => null,
        ], $overrides));
    }

    private function fakeModel(array $content): void
    {
        Http::fake([
            'api.meta.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($content)]]],
            ]),
        ]);
        Http::preventStrayRequests();
    }

    private function png(): string
    {
        return app(DemoReceiptImage::class)->generate(
            'S/ 50.00',
            'Luz Marina Torres',
            '28 oct 2026, 3:20 p. m.',
            '123',
            'TRIAL-1',
        );
    }
}
