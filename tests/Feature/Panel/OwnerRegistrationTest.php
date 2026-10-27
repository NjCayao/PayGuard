<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_register_with_business(): void
    {
        $response = $this->postJson('/api/panel/register', [
            'business_name' => 'Bodega El Sol',
            'name' => 'Maria Torres',
            'email' => 'maria@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'owner_whatsapp' => '+51 982 226 835',
        ]);

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
        $response = $this->postJson('/api/panel/register', [
            'business_name' => 'Bodega El Sol',
            'name' => 'Maria Torres',
            'email' => 'maria@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'other456',
            'owner_whatsapp' => '51982226835',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'maria@example.com']);

        $response = $this->postJson('/api/panel/register', [
            'business_name' => 'Bodega El Sol',
            'name' => 'Maria Torres',
            'email' => 'maria@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'owner_whatsapp' => '51982226835',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
    }

    public function test_registration_rejects_invalid_whatsapp(): void
    {
        $response = $this->postJson('/api/panel/register', [
            'business_name' => 'Bodega El Sol',
            'name' => 'Maria Torres',
            'email' => 'maria@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'owner_whatsapp' => 'not-a-number',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('owner_whatsapp');
    }
}
