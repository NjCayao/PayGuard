<?php

namespace Tests\Feature\Meta;

use App\Models\Verification;
use App\Services\Meta\MetaModelException;
use App\Services\Meta\ScreenshotReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScreenshotReaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payguard.meta.base_url', 'https://api.meta.ai/v1');
        config()->set('payguard.meta.api_key', 'test-key');
        Storage::fake('local');
    }

    public function test_reads_full_screenshot(): void
    {
        $this->fakeModel([
            'wallet' => 'yape',
            'amount' => '50.00',
            'recipient_name' => 'Maria Torres',
            'paid_date' => '2026-10-28',
            'paid_time' => '15:20',
            'security_code' => '123',
            'operation_code' => 'OP-1',
            'edit_signals' => [],
        ]);

        $verification = $this->verificationWithMedia();

        $data = app(ScreenshotReader::class)->read($verification);

        $this->assertSame('yape', $data->wallet);
        $this->assertSame('50.00', $data->amount);
        $this->assertSame('Maria Torres', $data->recipientName);
        $this->assertSame('2026-10-28 20:20:00', $data->paidAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $data->paidAt->timezoneName);
        $this->assertSame('123', $data->securityCode);
        $this->assertSame('OP-1', $data->operationCode);
        $this->assertSame([], $data->editSignals);
        $this->assertSame('50.00', $data->raw['amount']);
        $this->assertIsInt($data->aiMs);

        Http::assertSent(function ($request) {
            $content = $request['messages'][1]['content'];

            return $request->url() === 'https://api.meta.ai/v1/chat/completions'
                && $request['response_format']['type'] === 'json_schema'
                && str_starts_with($content[1]['image_url']['url'], 'data:image/jpeg;base64,');
        });
    }

    public function test_apply_to_fills_verification(): void
    {
        $this->fakeModel([
            'wallet' => 'plin',
            'amount' => '20,50',
            'recipient_name' => '  Juan   Perez ',
            'paid_date' => '2026-10-28',
            'paid_time' => '09:05',
            'security_code' => '007',
            'operation_code' => null,
            'edit_signals' => ['Blurry digits'],
        ]);

        $verification = $this->verificationWithMedia();

        app(ScreenshotReader::class)->read($verification)->applyTo($verification);

        $verification->refresh();

        $this->assertSame('plin', $verification->wallet);
        $this->assertSame('20.50', $verification->amount);
        $this->assertSame('Juan Perez', $verification->recipient_name);
        $this->assertSame('2026-10-28 14:05:00', $verification->paid_at->format('Y-m-d H:i:s'));
        $this->assertSame('007', $verification->security_code);
        $this->assertNull($verification->operation_code);
        $this->assertSame(['Blurry digits'], $verification->edit_signals);
        $this->assertSame('20,50', $verification->extracted['amount']);
        $this->assertIsInt($verification->ai_ms);
    }

    public function test_missing_fields_become_null_with_sane_defaults(): void
    {
        $this->fakeModel([
            'wallet' => 'unknown',
            'amount' => 'not a number',
            'recipient_name' => '',
            'paid_date' => null,
            'paid_time' => null,
            'security_code' => '12',
            'operation_code' => '',
            'edit_signals' => null,
        ]);

        $verification = $this->verificationWithMedia();

        $data = app(ScreenshotReader::class)->read($verification);

        $this->assertSame('yape', $data->wallet);
        $this->assertNull($data->amount);
        $this->assertNull($data->recipientName);
        $this->assertLessThanOrEqual(120, abs($data->paidAt->timestamp - time()));
        $this->assertNull($data->securityCode);
        $this->assertNull($data->operationCode);
        $this->assertSame([], $data->editSignals);
    }

    public function test_throws_when_api_fails(): void
    {
        Http::fake(['api.meta.ai/*' => Http::response(['error' => 'boom'], 500)]);

        $this->expectException(MetaModelException::class);

        app(ScreenshotReader::class)->read($this->verificationWithMedia());
    }

    public function test_throws_when_file_is_missing(): void
    {
        Http::fake();

        $verification = Verification::factory()->create(['media_path' => 'whatsapp/1/missing.jpg']);

        $this->expectException(MetaModelException::class);

        app(ScreenshotReader::class)->read($verification);
    }

    public function test_throws_on_unsupported_extension(): void
    {
        Http::fake();

        Storage::disk('local')->put('whatsapp/1/1.bmp', 'bytes');
        $verification = Verification::factory()->create(['media_path' => 'whatsapp/1/1.bmp']);

        $this->expectException(MetaModelException::class);

        app(ScreenshotReader::class)->read($verification);
    }

    private function verificationWithMedia(): Verification
    {
        Storage::disk('local')->put('whatsapp/1/1.jpg', 'fake-image-bytes');

        return Verification::factory()->create([
            'media_path' => 'whatsapp/1/1.jpg',
            'media_sha256' => hash('sha256', 'fake-image-bytes'),
        ]);
    }

    private function fakeModel(array $content): void
    {
        Http::fake([
            'api.meta.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($content)]]],
            ]),
        ]);
    }
}
