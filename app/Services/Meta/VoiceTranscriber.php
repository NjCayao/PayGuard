<?php

namespace App\Services\Meta;

use App\Models\Verification;
use App\Services\Support\AudioConverter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class VoiceTranscriber
{
    public function __construct(private AudioConverter $converter) {}

    public function transcribe(Verification $verification): TranscriptData
    {
        $disk = Storage::disk(config('payguard.whatsapp.media_disk'));

        if ($verification->media_path === null || ! $disk->exists($verification->media_path)) {
            throw new MetaModelException('Audio file is missing.');
        }

        $extension = strtolower(pathinfo($verification->media_path, PATHINFO_EXTENSION));
        $input = tempnam(sys_get_temp_dir(), 'payguard-in-').".{$extension}";
        $wav = tempnam(sys_get_temp_dir(), 'payguard-wav-').'.wav';

        try {
            file_put_contents($input, $disk->get($verification->media_path));
            $this->converter->toWav($input, $wav);

            $wavBytes = file_get_contents($wav);

            if ($wavBytes === false || $wavBytes === '') {
                throw new MetaModelException('Converted audio is empty.');
            }

            return $this->send($verification, $wavBytes);
        } finally {
            @unlink($input);
            @unlink($wav);
        }
    }

    private function send(Verification $verification, string $wavBytes): TranscriptData
    {
        $url = rtrim((string) config('payguard.meta.base_url'), '/')
            ."/asr/transcribe?sessionId=v{$verification->id}";

        $request = [
            'model' => config('payguard.meta.transcribe_model'),
            'audioEncoding' => 'WAV',
            'mode' => config('payguard.meta.asr_mode'),
            'languageBias' => config('payguard.meta.asr_language_bias'),
            'keywords' => config('payguard.meta.asr_keywords'),
        ];

        $start = microtime(true);

        try {
            $response = Http::withToken((string) config('payguard.meta.api_key'))
                ->timeout((int) config('payguard.meta.transcribe_timeout'))
                ->acceptJson()
                ->asMultipart()
                ->post($url, [
                    [
                        'name' => 'request',
                        'contents' => json_encode($request),
                        'headers' => ['Content-Type' => 'application/json'],
                    ],
                    [
                        'name' => 'audio',
                        'contents' => $wavBytes,
                        'filename' => 'audio.wav',
                        'headers' => ['Content-Type' => 'audio/wav'],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new MetaModelException('Meta Model API request failed.', previous: $e);
        }

        $ms = (int) round((microtime(true) - $start) * 1000);

        if (! $response->successful()) {
            throw new MetaModelException("Meta Model API error: HTTP {$response->status()}.");
        }

        $data = $response->json();

        if (! is_array($data) || ! array_key_exists('transcript', $data)) {
            throw new MetaModelException('Meta Model API returned an invalid transcript.');
        }

        $text = trim((string) $data['transcript']);

        return new TranscriptData(
            text: $text === '' ? null : mb_substr($text, 0, 2000),
            audioDurationMs: is_int($data['audioDurationMs'] ?? null) ? $data['audioDurationMs'] : null,
            aiMs: $ms,
            raw: $data,
        );
    }
}
