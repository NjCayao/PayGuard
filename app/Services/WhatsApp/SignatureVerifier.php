<?php

namespace App\Services\WhatsApp;

class SignatureVerifier
{
    public function valid(string $rawBody, ?string $header): bool
    {
        $secret = (string) config('payguard.whatsapp.app_secret');

        if ($secret === '' || $header === null || $header === '') {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $rawBody, $secret), $header);
    }
}
