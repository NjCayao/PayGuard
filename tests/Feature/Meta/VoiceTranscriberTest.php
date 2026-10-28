<?php

namespace Tests\Feature\Meta;

use App\Models\Verification;
use App\Services\Meta\MetaModelException;
use App\Services\Meta\VoiceTranscriber;
use App\Services\Support\AudioConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoiceTranscriberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payguard.meta.base_url', 'https://api.meta.ai/v1');
        config()->set('payguard.meta.api_key', 'test-key');
        Storage::fake('local');

        app()->instance(AudioConverter::class, new class extends AudioConverter
        {
            public function toWav(string $inputPath, string $outputPath): void
            {
                file_put_contents($outputPath, 'fake-wav-bytes');
            }
        });
    }

    public function test_transcribes_audio_with_dedicated_endpoint(): void
    {
        Http::fake([
            'api.meta.ai/*' => Http::response([
                'sessionId' => 'v1',
                'transcript' => 'yape de cincuenta soles',
                'audioDurationMs' => 3200,
                'turns' => [],
            ]),
        ]);

        $verification = $this->verificationWithAudio();

        $result = app(VoiceTranscriber::class)->transcribe($verification);

        $this->assertSame('yape de cincuenta soles', $result->text);
        $this->assertSame(3200, $result->audioDurationMs);
        $this->assertIsInt($result->aiMs);

        Http::assertSent(function ($request) use ($verification) {
            return $request->method() === 'POST'
                && str_starts_with($request->url(), 'https://api.meta.ai/v1/asr/transcribe')
                && str_contains($request->url(), "sessionId=v{$verification->id}")
                && $request->hasHeader('Authorization');
        });
    }

    public function test_empty_transcript_becomes_null(): void
    {
        Http::fake([
            'api.meta.ai/*' => Http::response([
                'transcript' => '   ',
                'audioDurationMs' => 500,
                'turns' => [],
            ]),
        ]);

        $result = app(VoiceTranscriber::class)->transcribe($this->verificationWithAudio());

        $this->assertNull($result->text);
    }

    public function test_throws_when_api_fails(): void
    {
        Http::fake(['api.meta.ai/*' => Http::response(['error' => 'boom'], 500)]);

        $this->expectException(MetaModelException::class);

        app(VoiceTranscriber::class)->transcribe($this->verificationWithAudio());
    }

    public function test_throws_on_invalid_response(): void
    {
        Http::fake(['api.meta.ai/*' => Http::response(['nope' => true])]);

        $this->expectException(MetaModelException::class);

        app(VoiceTranscriber::class)->transcribe($this->verificationWithAudio());
    }

    public function test_throws_when_file_is_missing(): void
    {
        Http::fake();

        $verification = Verification::factory()->create(['media_path' => 'whatsapp/1/missing.ogg']);

        $this->expectException(MetaModelException::class);

        app(VoiceTranscriber::class)->transcribe($verification);
    }

    private function verificationWithAudio(): Verification
    {
        Storage::disk('local')->put('whatsapp/1/1.ogg', 'fake-audio-bytes');

        return Verification::factory()->create([
            'input_type' => 'audio',
            'media_path' => 'whatsapp/1/1.ogg',
            'media_sha256' => hash('sha256', 'fake-audio-bytes'),
        ]);
    }
}
