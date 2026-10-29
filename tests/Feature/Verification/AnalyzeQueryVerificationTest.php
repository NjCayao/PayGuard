<?php

namespace Tests\Feature\Verification;

use App\Jobs\AnalyzeQueryVerification;
use App\Jobs\RecheckPendingVerification;
use App\Models\Business;
use App\Models\Cashier;
use App\Models\Device;
use App\Models\ReceivedPayment;
use App\Models\Verification;
use App\Services\Meta\MetaModelException;
use App\Services\Meta\PaymentQueryParser;
use App\Services\Meta\QueryAmountFallback;
use App\Services\Meta\VoiceTranscriber;
use App\Services\Support\AudioConverter;
use App\Services\Verification\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AnalyzeQueryVerificationTest extends TestCase
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

        app()->instance(AudioConverter::class, new class extends AudioConverter
        {
            public function toWav(string $inputPath, string $outputPath): void
            {
                file_put_contents($outputPath, 'fake-wav-bytes');
            }
        });

        $this->business = Business::factory()->create();
        $this->cashier = Cashier::factory()->for($this->business)->create();
        $this->device = Device::factory()->for($this->business)->online()->create();
    }

    public function test_verifies_text_query_end_to_end(): void
    {
        $this->fakeQuery(['amount' => '50.00', 'payer_name' => 'Juan']);
        $payment = $this->payment();

        $verification = $this->textVerification('¿llegó un yape de 50 de Juan?');

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('verified', $verification->status);
        $this->assertSame('verified', $verification->reason_code);
        $this->assertSame($payment->id, $verification->received_payment_id);
        $this->assertSame('50.00', $verification->amount);
        $this->assertSame('Juan', $verification->extracted['query']['payer_name']);
        $this->assertSame('Yape recibido: S/ 50.00 de Juan Perez.', $verification->reason);
        $this->assertTextReply($verification, '✅ Yape recibido: S/ 50.00 de Juan Perez');
        $this->assertNoTemplate();
        Queue::assertNotPushed(RecheckPendingVerification::class);
    }

    public function test_verifies_audio_query_and_saves_transcript(): void
    {
        $this->fakeTranscribe('yape de cincuenta soles');
        $this->fakeQuery(['amount' => '50.00']);
        $payment = $this->payment();

        $verification = $this->audioVerification();

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('yape de cincuenta soles', $verification->transcript);
        $this->assertSame('verified', $verification->status);
        $this->assertSame($payment->id, $verification->received_payment_id);
        $this->assertIsInt($verification->ai_ms);
        $this->assertTextReply($verification, '✅ Yape recibido: S/ 50.00 de Juan Perez');
        $this->assertNoTemplate();
    }

    public function test_greeting_gets_help_without_ai(): void
    {
        $this->fakeWhatsApp();

        $verification = $this->textVerification('Hola, buenos días');

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('error', $verification->status);
        $this->assertSame('text_help', $verification->reason_code);
        $this->assertTextReply($verification, 'Hola, soy PayGuard');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.meta.ai'));
        Queue::assertNotPushed(RecheckPendingVerification::class);
    }

    public function test_query_without_amount_asks_for_amount_when_none_arrived(): void
    {
        $this->fakeQuery(['amount' => null, 'payer_name' => null]);

        $verification = $this->textVerification('¿llegó un yape?');

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('not_found', $verification->status);
        $this->assertSame('ask_amount', $verification->reason_code);
        $this->assertSame('¿De cuánto fue el Yape? Escríbeme, por ejemplo: yape de 50.', $verification->reason);
        $this->assertTextReply($verification, '⚠ ¿De cuánto fue el Yape?');
        $this->assertNoTemplate();
        Queue::assertNotPushed(RecheckPendingVerification::class);
    }

    public function test_query_without_amount_verifies_single_recent_payment(): void
    {
        $this->fakeQuery(['amount' => null, 'payer_name' => null]);
        $payment = $this->payment(['received_at' => now()->subMinute()]);

        $verification = $this->textVerification('me yapearon');

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('verified', $verification->status);
        $this->assertSame('amountless_single', $verification->reason_code);
        $this->assertSame($payment->id, $verification->received_payment_id);
        $this->assertTextReply($verification, '✅ Yape recibido: S/ 50.00 de Juan Perez a las ');
        $this->assertNoTemplate();
        Queue::assertNotPushed(RecheckPendingVerification::class);
    }

    public function test_query_without_amount_lists_several_recent_payments(): void
    {
        $this->fakeQuery(['amount' => null, 'payer_name' => null]);

        $this->payment(['amount' => '50.00', 'payer_name' => 'Antoni Mon*', 'received_at' => now()->subMinutes(2)]);
        $this->payment(['amount' => '20.00', 'payer_name' => 'Sonia Vit*', 'received_at' => now()->subMinute()]);

        $verification = $this->textVerification('revisa el yape');

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('not_found', $verification->status);
        $this->assertSame('amountless_multiple', $verification->reason_code);
        $this->assertNull($verification->received_payment_id);
        $this->assertStringStartsWith('Hay 2 pagos recientes sin revisar: ', $verification->reason);
        $this->assertStringContainsString('S/ 50.00 de Antoni Mon*', $verification->reason);
        $this->assertStringContainsString('S/ 20.00 de Sonia Vit*', $verification->reason);
        $this->assertTextReply($verification, '⚠ Hay 2 pagos recientes sin revisar: ');
        Queue::assertNotPushed(RecheckPendingVerification::class);
    }

    public function test_query_with_security_code_verifies_matching_payment(): void
    {
        $this->fakeQuery(['amount' => '50.00', 'security_code' => '944']);
        $payment = $this->payment(['security_code' => '944']);

        $verification = $this->textVerification('yape de 50, código 944');

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('verified', $verification->status);
        $this->assertSame($payment->id, $verification->received_payment_id);
        $this->assertSame('944', $verification->security_code);
        $this->assertTextReply($verification, '✅ Yape recibido: S/ 50.00 de Juan Perez · Cód. 944.');
    }

    public function test_query_with_wrong_security_code_is_mismatch(): void
    {
        $this->fakeQuery(['amount' => '50.00', 'security_code' => '944']);
        $this->payment(['security_code' => '123']);

        $verification = $this->textVerification('yape de 50, código 944');

        $this->runJob($verification);

        $this->assertSame('mismatch', $verification->fresh()->status);
        $this->assertSame('mismatch_code', $verification->fresh()->reason_code);
        $this->assertTextReply($verification, '❌ El código de seguridad no coincide.');
        Queue::assertNotPushed(RecheckPendingVerification::class);
    }

    public function test_query_with_unknown_security_code_is_not_found(): void
    {
        $this->fakeQuery(['amount' => '50.00', 'security_code' => '944']);

        $verification = $this->textVerification('yape de 50, código 944');

        $this->runJob($verification);

        $this->assertSame('not_found', $verification->fresh()->status);
        $this->assertTextReply($verification, '❌ No llegó ningún pago de S/ 50.00.');
        Queue::assertNotPushed(RecheckPendingVerification::class);
    }

    public function test_parser_failure_uses_rules_fallback(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
            'api.meta.ai/*' => Http::response(['error' => 'boom'], 500),
        ]);
        $payment = $this->payment();

        $verification = $this->textVerification('yape de 50');

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('verified', $verification->status);
        $this->assertSame($payment->id, $verification->received_payment_id);
        $this->assertTrue($verification->extracted['query']['is_payment_query']);
        $this->assertTextReply($verification, '✅ Yape recibido: S/ 50.00 de Juan Perez');
    }

    public function test_parser_failure_without_amount_asks_for_amount(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
            'api.meta.ai/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $verification = $this->textVerification('me yapearon');

        $this->runJob($verification);

        $this->assertSame('ask_amount', $verification->fresh()->reason_code);
        $this->assertTextReply($verification, '⚠ ¿De cuánto fue el Yape?');
    }

    public function test_transcriber_failure_throws_for_retry(): void
    {
        Http::fake(['api.meta.ai/*' => Http::response(['error' => 'boom'], 500)]);

        $verification = $this->audioVerification();

        try {
            $this->runJob($verification);
            $this->fail('The job should throw so the queue retries it.');
        } catch (MetaModelException) {
            $this->assertSame('processing', $verification->fresh()->status);
            $this->assertNull($verification->fresh()->transcript);
        }
    }

    public function test_retries_come_from_config(): void
    {
        config()->set('payguard.query.tries', 4);
        config()->set('payguard.query.backoff_seconds', 7);

        $job = new AnalyzeQueryVerification(1);

        $this->assertSame(4, $job->tries);
        $this->assertSame([7, 7, 7], $job->backoff);
    }

    public function test_goes_pending_when_payment_has_not_arrived(): void
    {
        $this->fakeQuery(['amount' => '50.00']);

        $verification = $this->textVerification('yape de 50');

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('pending', $verification->status);
        $this->assertNotNull($verification->recheck_until);
        $this->assertTextReply($verification, '⏳ Todavía no llega el pago de S/ 50.00.');
        Queue::assertPushed(RecheckPendingVerification::class, function ($job) use ($verification) {
            return $job->verificationId === $verification->id;
        });
    }

    public function test_multiple_matches_reply_without_claiming(): void
    {
        $this->fakeQuery(['amount' => '50.00']);

        $this->payment(['payer_name' => 'Antoni Mon*', 'received_at' => now()->subMinutes(4)]);
        $this->payment(['payer_name' => 'Sonia Vit*', 'received_at' => now()->subMinutes(3)]);

        $verification = $this->textVerification('yape de 50');

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('mismatch', $verification->status);
        $this->assertSame('multiple_matches', $verification->reason_code);
        $this->assertNull($verification->received_payment_id);
        $this->assertStringStartsWith('Hay 2 pagos por Yape de S/ 50.00 recientes: ', $verification->reason);
        $this->assertStringContainsString('Antoni Mon*', $verification->reason);
        $this->assertStringContainsString('Sonia Vit*', $verification->reason);
        $this->assertTextReply($verification, '⚠ Hay 2 pagos por Yape de S/ 50.00 recientes: ');
        $this->assertNoTemplate();
        Queue::assertNotPushed(RecheckPendingVerification::class);
    }

    public function test_already_confirmed_payment_replies_without_alert(): void
    {
        $this->fakeQuery(['amount' => '50.00']);
        $payment = $this->payment();

        Verification::factory()->for($this->business)->for($this->cashier)->create([
            'status' => 'verified',
            'received_payment_id' => $payment->id,
        ]);

        $verification = $this->textVerification('yape de 50');

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('reused', $verification->status);
        $this->assertSame('already_confirmed', $verification->reason_code);
        $this->assertStringStartsWith('Ese Yape ya fue confirmado a las ', $verification->reason);
        $this->assertTextReply($verification, '⚠ Ese Yape ya fue confirmado a las ');
        $this->assertNoTemplate();
        Queue::assertNotPushed(RecheckPendingVerification::class);
    }

    public function test_offline_query_warns_and_alerts_owner(): void
    {
        $this->fakeQuery(['amount' => '50.00']);
        $this->device->update(['last_seen_at' => now()->subMinutes(30)]);

        $verification = $this->textVerification('yape de 50');

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('pending', $verification->status);
        $this->assertSame('offline', $verification->reason_code);
        $this->assertTextReply($verification, '⚠ El celular del negocio no está conectado.');
        $this->assertTemplateSent();
        $this->assertDatabaseHas('owner_alerts', [
            'verification_id' => $verification->id,
            'status' => 'sent',
        ]);
    }

    public function test_skips_non_processing_verifications(): void
    {
        Http::fake();

        $verification = Verification::factory()->for($this->business)->for($this->cashier)->create([
            'input_type' => 'text',
            'status' => 'verified',
        ]);

        $this->runJob($verification);

        Http::assertNothingSent();
    }

    public function test_resumes_after_parse_without_calling_model_again(): void
    {
        $this->fakeWhatsApp();
        $this->payment();

        $verification = Verification::factory()->for($this->business)->for($this->cashier)->create([
            'status' => 'processing',
            'input_type' => 'text',
            'wallet' => 'yape',
            'amount' => '50.00',
            'extracted' => [
                'raw_text' => 'yape de 50',
                'query' => [
                    'is_payment_query' => true,
                    'wallet' => 'yape',
                    'amount' => '50.00',
                    'payer_name' => null,
                ],
            ],
        ]);

        $this->runJob($verification);

        $this->assertSame('verified', $verification->fresh()->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.meta.ai'));
    }

    public function test_failed_marks_error_and_replies(): void
    {
        $this->fakeWhatsApp();

        $verification = $this->textVerification('yape de 50');

        (new AnalyzeQueryVerification($verification->id))->failed(new RuntimeException('AI down'));

        $verification->refresh();

        $this->assertSame('error', $verification->status);
        $this->assertSame('No se pudo revisar tu consulta.', $verification->reason);
        $this->assertTextReply($verification, 'No pude revisar este pago. Inténtalo de nuevo en un momento.');
    }

    public function test_releases_job_when_verification_is_locked(): void
    {
        Http::fake();

        $verification = $this->textVerification('yape de 50');
        Cache::lock("analyze-query:{$verification->id}", 60)->acquire();

        $job = (new AnalyzeQueryVerification($verification->id))->withFakeQueueInteractions();
        $job->handle(app(VoiceTranscriber::class), app(PaymentQueryParser::class), app(QueryAmountFallback::class), app(VerificationService::class));

        $job->assertReleased();
        Http::assertNothingSent();
    }

    private function textVerification(string $text): Verification
    {
        return Verification::factory()->for($this->business)->for($this->cashier)->create([
            'input_type' => 'text',
            'status' => 'processing',
            'extracted' => ['raw_text' => $text],
        ]);
    }

    private function audioVerification(): Verification
    {
        $verification = Verification::factory()->for($this->business)->for($this->cashier)->create([
            'input_type' => 'audio',
            'status' => 'processing',
        ]);

        $path = "whatsapp/{$this->business->id}/{$verification->id}.ogg";
        Storage::disk('local')->put($path, 'fake-audio-bytes');
        $verification->update(['media_path' => $path, 'media_sha256' => hash('sha256', 'fake-audio-bytes')]);

        return $verification;
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

    private function fakeQuery(array $overrides = []): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
            'api.meta.ai/v1/asr/*' => Http::response([
                'transcript' => 'yape de cincuenta',
                'audioDurationMs' => 2500,
                'turns' => [],
            ]),
            'api.meta.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(array_merge([
                    'is_payment_query' => true,
                    'wallet' => 'yape',
                    'amount' => '50.00',
                    'payer_name' => null,
                ], $overrides))]]],
            ]),
        ]);
    }

    private function fakeTranscribe(string $transcript): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
            'api.meta.ai/v1/asr/*' => Http::response([
                'transcript' => $transcript,
                'audioDurationMs' => 2500,
                'turns' => [],
            ]),
            'api.meta.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'is_payment_query' => true,
                    'wallet' => 'yape',
                    'amount' => '50.00',
                    'payer_name' => null,
                ])]]],
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
        (new AnalyzeQueryVerification($verification->id))
            ->handle(app(VoiceTranscriber::class), app(PaymentQueryParser::class), app(QueryAmountFallback::class), app(VerificationService::class));
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
