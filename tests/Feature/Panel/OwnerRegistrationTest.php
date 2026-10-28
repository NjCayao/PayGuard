<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OwnerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Bodega El Sol',
            'name' => 'Maria Torres',
            'email' => 'maria@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'owner_whatsapp' => '+51 982 226 835',
            'cf-turnstile-response' => 'test-token',
        ], $overrides);
    }

    private function fakeTurnstile(bool $success = true): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => $success]),
        ]);
    }

    public function test_owner_can_register_with_business(): void
    {
        $this->fakeTurnstile();

        $response = $this->postJson('/api/panel/register', $this->payload());

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'maria@example.com');
        $response->assertJsonPath('data.business.name', 'Bodega El Sol');

        $this->assertDatabaseHas('businesses', [
            'name' => 'Bodega El Sol',
            'owner_whatsapp' => '51982226835',
            'match_window_minutes' => 10,
            'recheck_minutes' => 2,
            'timezone' => 'America/Lima',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.com',
            'business_id' => $response->json('data.business.id'),
        ]);

        $this->assertAuthenticated('web');
    }

    public function test_registration_requires_matching_passwords(): void
    {
        $this->fakeTurnstile();

        $response = $this->postJson('/api/panel/register', $this->payload([
            'password_confirmation' => 'other456',
        ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $this->fakeTurnstile();

        User::factory()->create(['email' => 'maria@example.com']);

        $response = $this->postJson('/api/panel/register', $this->payload());

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
    }

    public function test_registration_rejects_invalid_whatsapp(): void
    {
        $this->fakeTurnstile();

        $response = $this->postJson('/api/panel/register', $this->payload([
            'owner_whatsapp' => 'not-a-number',
        ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('owner_whatsapp');
    }

    public function test_registration_rejects_filled_honeypot(): void
    {
        $this->fakeTurnstile();

        $response = $this->postJson('/api/panel/register', $this->payload([
            'website' => 'http://spam.example',
        ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('website');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_requires_turnstile_token(): void
    {
        $payload = $this->payload();
        unset($payload['cf-turnstile-response']);

        $response = $this->postJson('/api/panel/register', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('cf-turnstile-response');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_failed_turnstile(): void
    {
        $this->fakeTurnstile(false);

        $response = $this->postJson('/api/panel/register', $this->payload());

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('cf-turnstile-response');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_is_throttled_per_ip(): void
    {
        $this->fakeTurnstile();

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/panel/register', $this->payload([
                'email' => "owner{$i}@example.com",
            ]));
            $response->assertCreated();

            Auth::guard('web')->logout();
        }

        $response = $this->postJson('/api/panel/register', $this->payload([
            'email' => 'owner6@example.com',
        ]));

        $response->assertStatus(429);
        $this->assertDatabaseCount('users', 5);
    }
}
