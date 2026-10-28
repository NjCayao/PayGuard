<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsApp\SignatureVerifier;
use Tests\TestCase;

class SignatureVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payguard.whatsapp.app_secret', 'test-secret');
    }

    public function test_accepts_valid_signature(): void
    {
        $body = '{"object":"whatsapp_business_account"}';
        $header = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

        $this->assertTrue(app(SignatureVerifier::class)->valid($body, $header));
    }

    public function test_rejects_wrong_signature(): void
    {
        $this->assertFalse(app(SignatureVerifier::class)->valid('body', 'sha256=wrong'));
    }

    public function test_rejects_missing_header(): void
    {
        $this->assertFalse(app(SignatureVerifier::class)->valid('body', null));
    }

    public function test_rejects_when_secret_is_not_configured(): void
    {
        config()->set('payguard.whatsapp.app_secret', '');

        $this->assertFalse(app(SignatureVerifier::class)->valid('body', 'sha256=anything'));
    }
}
