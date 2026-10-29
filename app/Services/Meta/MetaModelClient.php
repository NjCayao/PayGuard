<?php

namespace App\Services\Meta;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class MetaModelClient
{
    public function complete(array $messages, array $responseFormat, ?int $maxTokens = null, ?int $timeout = null): array
    {
        try {
            $response = Http::withToken((string) config('payguard.meta.api_key'))
                ->timeout($timeout ?? (int) config('payguard.meta.vision_timeout'))
                ->acceptJson()
                ->post($this->url('/chat/completions'), [
                    'model' => config('payguard.meta.vision_model'),
                    'temperature' => 0,
                    'max_tokens' => $maxTokens ?? (int) config('payguard.meta.vision_max_tokens'),
                    'messages' => $messages,
                    'response_format' => $responseFormat,
                ]);
        } catch (ConnectionException $e) {
            throw new MetaModelException('Meta Model API request failed.', previous: $e);
        }

        if (! $response->successful()) {
            throw new MetaModelException("Meta Model API error: HTTP {$response->status()}.");
        }

        $choice = $response->json('choices.0', []);
        $content = is_array($choice) ? ($choice['message']['content'] ?? null) : null;

        if (! is_string($content) || $content === '') {
            $finishReason = is_array($choice) ? ($choice['finish_reason'] ?? 'unknown') : 'unknown';

            Log::warning('Meta Model API returned an empty response.', [
                'model' => config('payguard.meta.vision_model'),
                'finish_reason' => $finishReason,
                'usage' => $response->json('usage', []),
                'max_tokens' => $maxTokens ?? (int) config('payguard.meta.vision_max_tokens'),
            ]);

            throw new MetaModelException(
                "Meta Model API returned an empty response (finish_reason: {$finishReason})."
            );
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new MetaModelException('Meta Model API returned invalid JSON.', previous: $e);
        }

        if (! is_array($decoded)) {
            throw new MetaModelException('Meta Model API returned invalid JSON.');
        }

        return $decoded;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('payguard.meta.base_url'), '/').$path;
    }
}
