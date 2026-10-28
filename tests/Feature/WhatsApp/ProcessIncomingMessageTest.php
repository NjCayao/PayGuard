<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\ProcessIncomingMessage;
use App\Models\Cashier;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
    }

    public function test_ignores_duplicate_message(): void
    {
        $this->fakeMedia('image/jpeg', 'fake-image-bytes');

        $cashier = Cashier::factory()->create();

        $message = [
            'wa_message_id' => 'wamid.dupe',
            'from' => $cashier->whatsapp,
            'type' => 'image',
            'media_id' => 'media1',
            'text' => null,
        ];

        ProcessIncomingMessage::dispatch($message);
        ProcessIncomingMessage::dispatch($message);

        $this->assertDatabaseCount('verifications', 1);
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
                && $data['text']['body'] === 'Envíame la captura del Yape (imagen) o un audio.'
                && $data['context']['message_id'] === 'wamid.vid';
        });
    }

    public function test_stores_text_query(): void
    {
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
        $this->assertNotEmpty($verification->fresh()->reason);
        Http::assertSent(function ($request) use ($cashier) {
            $data = $request->data();

            return $data['to'] === $cashier->whatsapp
                && $data['text']['body'] === 'No pude revisar este pago. Inténtalo de nuevo en un momento.';
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
}
