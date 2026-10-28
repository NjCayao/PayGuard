<?php

namespace Tests\Unit\Security;

use App\Services\Security\TurnstileVerifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TurnstileVerifierTest extends TestCase
{
    public function test_passes_when_cloudflare_confirms(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $this->assertTrue(app(TurnstileVerifier::class)->passes('token', '127.0.0.1'));
    }

    public function test_fails_when_cloudflare_rejects(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false]),
        ]);

        $this->assertFalse(app(TurnstileVerifier::class)->passes('token', '127.0.0.1'));
    }

    public function test_fails_without_calling_cloudflare_when_token_is_empty(): void
    {
        Http::fake();

        $this->assertFalse(app(TurnstileVerifier::class)->passes(null, '127.0.0.1'));

        Http::assertNothingSent();
    }
}
