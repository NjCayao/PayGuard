<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_login(): void
    {
        User::factory()->create([
            'email' => 'maria@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/panel/login', [
            'email' => 'maria@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.email', 'maria@example.com');
        $this->assertAuthenticated('web');
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'maria@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/panel/login', [
            'email' => 'maria@example.com',
            'password' => 'wrong-pass',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
        $this->assertGuest('web');
    }

    public function test_guest_cannot_fetch_user(): void
    {
        $this->getJson('/api/panel/user')->assertUnauthorized();
    }

    public function test_owner_can_fetch_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/panel/user');

        $response->assertOk();
        $response->assertJsonPath('data.email', $user->email);
        $response->assertJsonPath('data.business.id', $user->business_id);
    }

    public function test_owner_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/panel/logout');

        $response->assertOk();
        $this->assertGuest('web');
    }
}
