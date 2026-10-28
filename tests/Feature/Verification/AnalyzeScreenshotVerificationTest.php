<?php

namespace Tests\Feature\Verification;

use App\Jobs\AnalyzeScreenshotVerification;
use App\Jobs\RecheckPendingVerification;
use App\Models\Business;
use App\Models\Cashier;
use App\Models\Device;
use App\Models\ReceivedPayment;
use App\Models\Verification;
use App\Services\Meta\MetaModelException;
use App\Services\Meta\ScreenshotReader;
use App\Services\Verification\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AnalyzeScreenshotVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Cashier $cashier;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payguard.whatsapp.token', 'test-token');
        config()->set('payguard.whatsapp.phone_number_id', '12345');
        config()->set('payguard.meta.base_url', 'https://api.meta.ai/v1');
        config()->set('payguard.meta.api_key', 'test-key');
        Storage::fake('local');
        Queue::fake([RecheckPendingVerification::class]);

        $this->business = Business::factory()->create(['yape_holder_name' => 'Maria Torres']);
        $this->cashier = Cashier::factory()->for($this->business)->create();
        $this->device = Device::factory()->for($this->business)->online()->create();
    }

    public function test_verifies_matching_payment_end_to_end(): void
    {
        $this->fakeModel($this->screenshot());
        $payment = $this->payment();

        $verification = $this->verificationWithImage();

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('verified', $verification->status);
        $this->assertSame('verified', $verification->reason_code);
        $this->assertSame($payment->id, $verification->received_payment_id);
        $this->assertSame('Maria Torres', $verification->recipient_name);
        $this->assertSame('50.00', $verification->amount);
        $this->assertIsInt($verification->ai_ms);
        $this->assertSame('Yape recibido: S/ 50.00 de Juan Perez.', $verification->reason);
        $this->assertTextReply($verification, '✅ Yape recibido: S/ 50.00 de Juan Perez');
        $this->assertNoTemplate();
        Queue::assertNotPushed(RecheckPendingVerification::class);
    }

    public function test_goes_pending_when_payment_has_not_arrived(): void
    {
        $this->fakeModel($this->screenshot());

        $verification = $this->verificationWithImage();

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('pending', $verification->status);
        $this->assertNotNull($verification->recheck_until);
        $this->assertTextReply($verification, '⏳ Todavía no llega el pago de S/ 50.00.');
        Queue::assertPushed(RecheckPendingVerification::class, function ($job) use ($verification) {
            return $job->verificationId === $verification->id;
        });
    }

    public function test_reused_when_previous_verification_is_verified(): void
    {
        $this->fakeModel($this->screenshot(['operation_code' => 'OP-1']));
        $payment = $this->payment();

        Verification::factory()->for($this->business)->for($this->cashier)->create([
            'status' => 'verified',
            'operation_code' => 'OP-1',
        ]);

        $verification = $this->verificationWithImage();

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('reused', $verification->status);
        $this->assertSame('reused', $verification->reason_code);
        $this->assertSame('Esta captura ya se usó antes.', $verification->reason);
        $this->assertNull($verification->received_payment_id);
        $this->assertTextReply($verification, '❌ Esta captura ya se usó antes.');
        $this->assertTemplateSent();
        $this->assertNull($payment->fresh()->verification);
    }

    public function test_duplicate_of_pending_reports_already_reviewing(): void
    {
        $this->fakeModel($this->screenshot(['operation_code' => 'OP-1']));
        $payment = $this->payment();

        Verification::factory()->for($this->business)->for($this->cashier)->create([
            'status' => 'pending',
            'operation_code' => 'OP-1',
            'recheck_until' => now()->addMinutes(2),
        ]);

        $verification = $this->verificationWithImage();

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('pending', $verification->status);
        $this->assertTextReply($verification, '⏳ Ya estoy revisando esta captura.');
        $this->assertNull($payment->fresh()->verification);
        $this->assertNoTemplate();
        Queue::assertPushed(RecheckPendingVerification::class);
    }

    public function test_copies_rejected_result(): void
    {
        $this->fakeModel($this->screenshot(['operation_code' => 'OP-1']));

        Verification::factory()->for($this->business)->for($this->cashier)->create([
            'status' => 'not_found',
            'reason' => 'No llegó ningún pago de S/ 50.00.',
            'operation_code' => 'OP-1',
        ]);

        $verification = $this->verificationWithImage();

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('not_found', $verification->status);
        $this->assertSame('No llegó ningún pago de S/ 50.00.', $verification->reason);
        $this->assertTextReply($verification, '❌ No llegó ningún pago de S/ 50.00.');
        $this->assertNoTemplate();
    }

    public function test_reused_when_matching_payment_is_already_claimed(): void
    {
        $this->fakeModel($this->screenshot());
        $payment = $this->payment();

        Verification::factory()->for($this->business)->for($this->cashier)->create([
            'status' => 'verified',
            'received_payment_id' => $payment->id,
        ]);

        $verification = $this->verificationWithImage();

        $this->runJob($verification);

        $this->assertSame('reused', $verification->fresh()->status);
        $this->assertTextReply($verification, '❌ Esta captura ya se usó antes.');
        $this->assertTemplateSent();
    }

    public function test_unreadable_screenshot_is_not_found(): void
    {
        $this->fakeModel($this->screenshot(['amount' => null]));

        $verification = $this->verificationWithImage();

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('not_found', $verification->status);
        $this->assertSame('No pude leer los datos de la captura.', $verification->reason);
        $this->assertTextReply($verification, '❌ No pude leer los datos de la captura.');
        Queue::assertNotPushed(RecheckPendingVerification::class);
    }

    public function test_stale_capture_goes_straight_to_not_found(): void
    {
        $this->fakeModel($this->screenshot([
            'paid_date' => now('America/Lima')->subDay()->toDateString(),
        ]));

        $verification = $this->verificationWithImage();

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('not_found', $verification->status);
        $this->assertSame('stale', $verification->reason_code);
        $this->assertStringStartsWith('No encontré ningún Yape de S/ 50.00 cerca de las ', $verification->reason);
        $this->assertTextReply($verification, '❌ No encontré ningún Yape de S/ 50.00 cerca de las ');
        Queue::assertNotPushed(RecheckPendingVerification::class);
    }

    public function test_mismatch_alerts_owner(): void
    {
        $this->fakeModel($this->screenshot(['recipient_name' => 'Pedro Lopez']));
        $this->payment();

        $verification = $this->verificationWithImage();

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('mismatch', $verification->status);
        $this->assertTextReply($verification, '❌ El pago es para otro titular, no para este negocio.');
        $this->assertTemplateSent();
        $this->assertDatabaseHas('owner_alerts', [
            'business_id' => $this->business->id,
            'verification_id' => $verification->id,
            'to' => $this->business->owner_whatsapp,
            'status' => 'sent',
            'wa_message_id' => 'wamid.resp',
        ]);
    }

    public function test_stale_with_edit_signals_alerts_owner(): void
    {
        $this->fakeModel($this->screenshot([
            'paid_date' => now('America/Lima')->subDay()->toDateString(),
            'edit_signals' => ['Blurry digits'],
        ]));

        $verification = $this->verificationWithImage();

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('not_found', $verification->status);
        $this->assertStringEndsWith('Además, la imagen muestra señales de edición.', $verification->reason);
        $this->assertTemplateSent();
    }

    public function test_skips_non_processing_verifications(): void
    {
        Http::fake();

        $verification = Verification::factory()->for($this->business)->for($this->cashier)->create([
            'status' => 'verified',
        ]);

        $this->runJob($verification);

        Http::assertNothingSent();
    }

    public function test_resumes_after_ai_without_calling_model_again(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
        ]);
        $this->payment();

        $verification = Verification::factory()->for($this->business)->for($this->cashier)->create([
            'status' => 'processing',
            'input_type' => 'image',
            'media_path' => 'whatsapp/1/1.jpg',
            'media_sha256' => hash('sha256', 'fake-image-bytes'),
            'extracted' => ['wallet' => 'yape'],
            'wallet' => 'yape',
            'amount' => '50.00',
            'recipient_name' => 'Maria Torres',
            'paid_at' => now()->subMinutes(5),
        ]);

        $this->runJob($verification);

        $this->assertSame('verified', $verification->fresh()->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.meta.ai'));
    }

    public function test_ai_failure_throws_for_retry(): void
    {
        Http::fake(['api.meta.ai/*' => Http::response(['error' => 'boom'], 500)]);

        $verification = $this->verificationWithImage();

        try {
            $this->runJob($verification);
            $this->fail('The job should throw so the queue retries it.');
        } catch (MetaModelException) {
            $this->assertSame('processing', $verification->fresh()->status);
            $this->assertNull($verification->fresh()->extracted);
        }
    }

    public function test_failed_marks_error_and_replies(): void
    {
        $this->fakeWhatsApp();

        $verification = $this->verificationWithImage();

        (new AnalyzeScreenshotVerification($verification->id))->failed(new RuntimeException('AI down'));

        $verification->refresh();

        $this->assertSame('error', $verification->status);
        $this->assertSame('No se pudo leer la captura.', $verification->reason);
        $this->assertTextReply($verification, 'No pude revisar este pago. Inténtalo de nuevo en un momento.');
    }

    public function test_releases_job_when_verification_is_locked(): void
    {
        Http::fake();

        $verification = $this->verificationWithImage();
        Cache::lock("analyze:{$verification->id}", 60)->acquire();

        $job = (new AnalyzeScreenshotVerification($verification->id))->withFakeQueueInteractions();
        $job->handle(app(ScreenshotReader::class), app(VerificationService::class));

        $job->assertReleased();
        Http::assertNothingSent();
    }

    private function verificationWithImage(): Verification
    {
        $verification = Verification::factory()->for($this->business)->for($this->cashier)->create([
            'input_type' => 'image',
            'status' => 'processing',
        ]);

        $path = "whatsapp/{$this->business->id}/{$verification->id}.jpg";
        Storage::disk('local')->put($path, 'fake-image-bytes');
        $verification->update(['media_path' => $path, 'media_sha256' => hash('sha256', 'fake-image-bytes')]);

        return $verification;
    }

    private function screenshot(array $overrides = []): array
    {
        $now = now('America/Lima');

        return array_merge([
            'wallet' => 'yape',
            'amount' => '50.00',
            'recipient_name' => 'Maria Torres',
            'paid_date' => $now->toDateString(),
            'paid_time' => $now->copy()->subMinute()->format('H:i'),
            'security_code' => null,
            'operation_code' => null,
            'edit_signals' => [],
        ], $overrides);
    }

    private function payment(array $overrides = []): ReceivedPayment
    {
        return ReceivedPayment::factory()
            ->for($this->business)->for($this->device)
            ->create(array_merge([
                'amount' => '50.00',
                'wallet' => 'yape',
                'payer_name' => 'Juan Perez',
                'received_at' => now()->subMinute(),
            ], $overrides));
    }

    private function fakeModel(array $content): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
            'api.meta.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($content)]]],
            ]),
        ]);
    }

    private function fakeWhatsApp(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
        ]);
    }

    private function runJob(Verification $verification): void
    {
        (new AnalyzeScreenshotVerification($verification->id))
            ->handle(app(ScreenshotReader::class), app(VerificationService::class));
    }

    private function assertTextReply(Verification $verification, string $startsWith): void
    {
        Http::assertSent(function ($request) use ($verification, $startsWith) {
            $data = $request->data();

            return ($data['type'] ?? '') === 'text'
                && $data['to'] === $this->cashier->whatsapp
                && ($data['context']['message_id'] ?? '') === $verification->wa_message_id
                && str_starts_with($data['text']['body'], $startsWith);
        });
    }

    private function assertTemplateSent(): void
    {
        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['type'] ?? '') === 'template'
                && $data['to'] === $this->business->owner_whatsapp;
        });
    }

    private function assertNoTemplate(): void
    {
        Http::assertNotSent(fn ($request) => ($request->data()['type'] ?? '') === 'template');
    }
}
