<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\AnalyzeQueryVerification;
use App\Jobs\AnalyzeScreenshotVerification;
use App\Jobs\ProcessIncomingMessage;
use App\Models\Cashier;
use App\Models\Verification;
use App\Services\WhatsApp\MediaStore;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProcessIncomingMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payguard.whatsapp.token', 'test-token');
        config()->set('payguard.whatsapp.phone_number_id', '12345');
        Storage::fake('local');
        Queue::fake([AnalyzeScreenshotVerification::class, AnalyzeQueryVerification::class]);
    }

    public function test_replies_to_unknown_number_only_once(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
        ]);

        $message = [
            'wa_message_id' => 'wamid.unknown',
            'from' => '51999111222',
            'type' => 'image',
            'media_id' => 'media1',
            'text' => null,
        ];

        ProcessIncomingMessage::dispatch($message);
        ProcessIncomingMessage::dispatch($message);

        $this->assertDatabaseCount('verifications', 0);
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $data['to'] === '51999111222'
                && $data['text']['body'] === 'Este número no está autorizado en PayGuard. Pide al dueño que te registre.'
                && $data['context']['message_id'] === 'wamid.unknown';
        });
    }

    public function test_replies_to_inactive_cashier_as_unauthorized(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
        ]);

        $cashier = Cashier::factory()->create(['is_active' => false]);

        ProcessIncomingMessage::dispatch([
            'wa_message_id' => 'wamid.inactive',
            'from' => $cashier->whatsapp,
            'type' => 'text',
            'media_id' => null,
            'text' => 'Hola',
        ]);

        $this->assertDatabaseCount('verifications', 0);
        Http::assertSentCount(1);
    }

    public function test_stores_image_and_creates_verification(): void
    {
        $this->fakeMedia('image/jpeg', 'fake-image-bytes');

        $cashier = Cashier::factory()->create();

        ProcessIncomingMessage::dispatch([
            'wa_message_id' => 'wamid.img',
            'from' => $cashier->whatsapp,
            'type' => 'image',
            'media_id' => 'media1',
            'text' => null,
        ]);

        $verification = Verification::where('wa_message_id', 'wamid.img')->firstOrFail();

        $this->assertSame('processing', $verification->status);
        $this->assertSame('image', $verification->input_type);
        $this->assertSame($cashier->id, $verification->cashier_id);
        $this->assertSame(hash('sha256', 'fake-image-bytes'), $verification->media_sha256);
        Storage::disk('local')->assertExists($verification->media_path);
        Queue::assertPushed(AnalyzeScreenshotVerification::class, function ($job) use ($verification) {
            return $job->verificationId === $verification->id;
        });
    }

    public function test_stores_audio_and_creates_verification(): void
    {
        $this->fakeMedia('audio/ogg', 'fake-audio-bytes');

        $cashier = Cashier::factory()->create();

        ProcessIncomingMessage::dispatch([
            'wa_message_id' => 'wamid.aud',
            'from' => $cashier->whatsapp,
            'type' => 'audio',
            'media_id' => 'media2',
            'text' => null,
        ]);

        $verification = Verification::where('wa_message_id', 'wamid.aud')->firstOrFail();

        $this->assertSame('audio', $verification->input_type);
        $this->assertSame(hash('sha256', 'fake-audio-bytes'), $verification->media_sha256);
        Storage::disk('local')->assertExists($verification->media_path);
        Queue::assertNotPushed(AnalyzeScreenshotVerification::class);
        Queue::assertPushed(AnalyzeQueryVerification::class, function ($job) use ($verification) {
            return $job->verificationId === $verification->id;
        });
    }

    public function test_first_attempt_fails_and_retry_succeeds(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['url' => 'https://media.example/dl', 'mime_type' => 'image/jpeg']),
            'media.example/*' => Http::sequence()->pushStatus(500)->push('fake-image-bytes'),
        ]);

        $cashier = Cashier::factory()->create();

        $job = new ProcessIncomingMessage([
            'wa_message_id' => 'wamid.flaky',
            'from' => $cashier->whatsapp,
            'type' => 'image',
            'media_id' => 'media1',
            'text' => null,
        ]);

        try {
            $this->runJob($job);
            $this->fail('The first attempt should throw.');
        } catch (RequestException $e) {
            $verification = Verification::where('wa_message_id', 'wamid.flaky')->firstOrFail();
            $this->assertSame('processing', $verification->status);
            $this->assertNull($verification->media_path);
        }

        $this->runJob($job);

        $verification = Verification::where('wa_message_id', 'wamid.flaky')->firstOrFail();
        $this->assertSame(hash('sha256', 'fake-image-bytes'), $verification->media_sha256);
        Storage::disk('local')->assertExists($verification->media_path);
    }

    public function test_retry_resumes_processing_verification(): void
    {
        $this->fakeMedia('image/jpeg', 'fake-image-bytes');

        $cashier = Cashier::factory()->create();
        Verification::create([
            'business_id' => $cashier->business_id,
            'cashier_id' => $cashier->id,
            'wa_message_id' => 'wamid.stuck',
            'input_type' => 'image',
            'status' => 'processing',
        ]);

        ProcessIncomingMessage::dispatch([
            'wa_message_id' => 'wamid.stuck',
            'from' => $cashier->whatsapp,
            'type' => 'image',
            'media_id' => 'media1',
            'text' => null,
        ]);

        $verification = Verification::where('wa_message_id', 'wamid.stuck')->firstOrFail();
        $this->assertSame(hash('sha256', 'fake-image-bytes'), $verification->media_sha256);
        Storage::disk('local')->assertExists($verification->media_path);
    }

    public function test_redispatches_analysis_for_processing_verification_with_media(): void
    {
        Http::fake();

        $cashier = Cashier::factory()->create();
        Verification::create([
            'business_id' => $cashier->business_id,
            'cashier_id' => $cashier->id,
            'wa_message_id' => 'wamid.done',
            'input_type' => 'image',
            'media_path' => 'whatsapp/1/1.jpg',
            'media_sha256' => hash('sha256', 'fake-image-bytes'),
            'status' => 'processing',
        ]);

        ProcessIncomingMessage::dispatch([
            'wa_message_id' => 'wamid.done',
            'from' => $cashier->whatsapp,
            'type' => 'image',
            'media_id' => 'media1',
            'text' => null,
        ]);

        $this->assertDatabaseCount('verifications', 1);
        Http::assertNothingSent();
        Queue::assertPushed(AnalyzeScreenshotVerification::class);
    }

    public function test_ignores_redelivery_of_resolved_verification(): void
    {
        Http::fake();

        $cashier = Cashier::factory()->create();
        Verification::create([
            'business_id' => $cashier->business_id,
            'cashier_id' => $cashier->id,
            'wa_message_id' => 'wamid.resolved',
            'input_type' => 'image',
            'media_path' => 'whatsapp/1/1.jpg',
            'media_sha256' => hash('sha256', 'fake-image-bytes'),
            'status' => 'verified',
        ]);

        ProcessIncomingMessage::dispatch([
            'wa_message_id' => 'wamid.resolved',
            'from' => $cashier->whatsapp,
            'type' => 'image',
            'media_id' => 'media1',
            'text' => null,
        ]);

        $this->assertDatabaseCount('verifications', 1);
        Http::assertNothingSent();
        Queue::assertNotPushed(AnalyzeScreenshotVerification::class);
    }

    public function test_releases_job_when_message_is_locked(): void
    {
        Http::fake();

        $cashier = Cashier::factory()->create();
        Cache::lock('wa-msg:wamid.locked', 60)->acquire();

        $job = (new ProcessIncomingMessage([
            'wa_message_id' => 'wamid.locked',
            'from' => $cashier->whatsapp,
            'type' => 'image',
            'media_id' => 'media1',
            'text' => null,
        ]))->withFakeQueueInteractions();

        $this->runJob($job);

        $job->assertReleased();
        $this->assertDatabaseCount('verifications', 0);
        Http::assertNothingSent();
    }

    public function test_replies_to_unsupported_type_without_verification(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
        ]);

        $cashier = Cashier::factory()->create();

        ProcessIncomingMessage::dispatch([
            'wa_message_id' => 'wamid.vid',
            'from' => $cashier->whatsapp,
            'type' => 'unsupported',
            'media_id' => null,
            'text' => null,
        ]);

        $this->assertDatabaseCount('verifications', 0);
        Http::assertSent(function ($request) use ($cashier) {
            $data = $request->data();

            return $data['to'] === $cashier->whatsapp
                && $data['text']['body'] === 'Envíame la captura del pago (imagen), un audio o un mensaje con el monto.'
                && $data['context']['message_id'] === 'wamid.vid';
        });
    }

    public function test_text_message_dispatches_query_analysis(): void
    {
        Http::fake();

        $cashier = Cashier::factory()->create();

        ProcessIncomingMessage::dispatch([
            'wa_message_id' => 'wamid.txt',
            'from' => $cashier->whatsapp,
            'type' => 'text',
            'media_id' => null,
            'text' => 'Llego un Yape de 50?',
        ]);

        $verification = Verification::where('wa_message_id', 'wamid.txt')->firstOrFail();

        $this->assertSame('text', $verification->input_type);
        $this->assertSame(['raw_text' => 'Llego un Yape de 50?'], $verification->extracted);
        $this->assertSame('processing', $verification->status);
        Queue::assertNotPushed(AnalyzeScreenshotVerification::class);
        Queue::assertPushed(AnalyzeQueryVerification::class, function ($job) use ($verification) {
            return $job->verificationId === $verification->id;
        });
        Http::assertNothingSent();
    }

    public function test_failed_marks_verification_as_error_and_replies(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
        ]);

        $cashier = Cashier::factory()->create();
        $verification = Verification::create([
            'business_id' => $cashier->business_id,
            'cashier_id' => $cashier->id,
            'wa_message_id' => 'wamid.broken',
            'input_type' => 'image',
            'status' => 'processing',
        ]);

        (new ProcessIncomingMessage([
            'wa_message_id' => 'wamid.broken',
            'from' => $cashier->whatsapp,
            'type' => 'image',
            'media_id' => 'media1',
            'text' => null,
        ]))->failed(new RuntimeException('download failed'));

        $this->assertSame('error', $verification->fresh()->status);
        $this->assertSame('No se pudo descargar o revisar el mensaje.', $verification->fresh()->reason);
        Http::assertSent(function ($request) use ($cashier) {
            $data = $request->data();

            return $data['to'] === $cashier->whatsapp
                && $data['text']['body'] === 'No pude revisar este pago. Inténtalo de nuevo en un momento.'
                && $data['context']['message_id'] === 'wamid.broken';
        });
    }

    public function test_failed_replies_unauthorized_when_cashier_is_unknown(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
        ]);

        (new ProcessIncomingMessage([
            'wa_message_id' => 'wamid.ghost',
            'from' => '51999111333',
            'type' => 'image',
            'media_id' => 'media1',
            'text' => null,
        ]))->failed(new RuntimeException('send failed'));

        Http::assertSent(function ($request) {
            return $request->data()['text']['body'] === 'Este número no está autorizado en PayGuard. Pide al dueño que te registre.';
        });
    }

    private function fakeMedia(string $mime, string $bytes): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['url' => 'https://media.example/dl', 'mime_type' => $mime]),
            'media.example/*' => Http::response($bytes),
        ]);
    }

    private function runJob(ProcessIncomingMessage $job): void
    {
        $job->handle(app(WhatsAppClient::class), app(MediaStore::class));
    }
}
