<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TurnstileVerifier
{
    public function passes(?string $token, ?string $ip): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'secret' => config('payguard.registration.turnstile_secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]
            );

            return (bool) ($response->json('success') ?? false);
        } catch (Throwable $e) {
            Log::warning('Turnstile verification request failed.', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
