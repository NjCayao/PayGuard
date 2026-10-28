<?php

namespace Tests\Feature\Panel;

use App\Models\Business;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_views_account(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->getJson('/api/panel/account');

        $response->assertOk();
        $response->assertJsonPath('data.email', $owner->email);
        $response->assertJsonPath('data.business.is_demo', false);
        $response->assertJsonPath('data.business.owner_whatsapp', $owner->business->owner_whatsapp);
    }

    public function test_guest_cannot_access_account(): void
    {
        $this->getJson('/api/panel/account')->assertUnauthorized();
        $this->putJson('/api/panel/account/email', [])->assertUnauthorized();
        $this->putJson('/api/panel/account/password', [])->assertUnauthorized();
        $this->putJson('/api/panel/account/owner-whatsapp', [])->assertUnauthorized();
    }

    public function test_owner_can_change_email_with_current_password(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->putJson('/api/panel/account/email', [
            'email' => 'nuevo@example.com',
            'current_password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.email', 'nuevo@example.com');
        $this->assertSame('nuevo@example.com', $owner->fresh()->email);
    }

    public function test_email_change_rejects_wrong_current_password(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->putJson('/api/panel/account/email', [
            'email' => 'nuevo@example.com',
            'current_password' => 'wrong-pass',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.current_password.0', 'La contraseña actual no es correcta.');
        $this->assertSame($owner->email, $owner->fresh()->email);
    }

    public function test_email_change_rejects_duplicate_email(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($owner)->putJson('/api/panel/account/email', [
            'email' => $other->email,
            'current_password' => 'password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.email.0', 'Este correo ya está registrado.');
    }

    public function test_owner_can_change_password(): void
    {
        $owner = User::factory()->create(['email' => 'maria@example.com']);

        $this->actingAs($owner)->putJson('/api/panel/account/password', [
            'current_password' => 'password',
            'password' => 'new-secret-1',
            'password_confirmation' => 'new-secret-1',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-secret-1', $owner->fresh()->password));

        $this->postJson('/api/panel/login', [
            'email' => 'maria@example.com',
            'password' => 'new-secret-1',
        ])->assertOk();

        $this->postJson('/api/panel/login', [
            'email' => 'maria@example.com',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_password_change_rejects_invalid_values(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->putJson('/api/panel/account/password', [
            'current_password' => 'wrong-pass',
            'password' => 'new-secret-1',
            'password_confirmation' => 'new-secret-1',
        ])->assertUnprocessable();

        $this->actingAs($owner)->putJson('/api/panel/account/password', [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'La contraseña debe tener al menos 8 caracteres.');

        $this->actingAs($owner)->putJson('/api/panel/account/password', [
            'current_password' => 'password',
            'password' => 'new-secret-1',
            'password_confirmation' => 'other-secret-2',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'Las contraseñas no coinciden.');

        $this->assertTrue(Hash::check('password', $owner->fresh()->password));
    }

    public function test_password_change_closes_other_sessions_but_keeps_devices(): void
    {
        config()->set('session.driver', 'database');

        $owner = User::factory()->create(['email' => 'maria@example.com']);
        $device = Device::factory()->for($owner->business)->create();
        $device->createToken('app');

        DB::table('sessions')->insert([
            'id' => 'other-session-id',
            'user_id' => $owner->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->postJson('/api/panel/login', [
            'email' => 'maria@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->putJson('/api/panel/account/password', [
            'current_password' => 'password',
            'password' => 'new-secret-1',
            'password_confirmation' => 'new-secret-1',
        ])->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-id']);
        $this->getJson('/api/panel/user')->assertOk();
        $this->assertSame(1, $device->fresh()->tokens()->count());
    }

    public function test_owner_can_change_owner_whatsapp(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->putJson('/api/panel/account/owner-whatsapp', [
            'owner_whatsapp' => '+51 999 111 222',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.business.owner_whatsapp', '51999111222');
        $this->assertSame('51999111222', $owner->business->fresh()->owner_whatsapp);
    }

    public function test_owner_whatsapp_rejects_invalid_number(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->putJson('/api/panel/account/owner-whatsapp', [
            'owner_whatsapp' => '123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.owner_whatsapp.0', 'Escribe un número de WhatsApp válido.');
    }

    public function test_demo_account_cannot_change_email_password_or_whatsapp(): void
    {
        $owner = $this->demoOwner();
        $message = (string) config('payguard.demo.blocked_message');

        $this->actingAs($owner)->putJson('/api/panel/account/email', [
            'email' => 'nuevo@example.com',
            'current_password' => 'password',
        ])->assertForbidden()->assertJsonPath('message', $message);

        $this->actingAs($owner)->putJson('/api/panel/account/password', [
            'current_password' => 'password',
            'password' => 'new-secret-1',
            'password_confirmation' => 'new-secret-1',
        ])->assertForbidden()->assertJsonPath('message', $message);

        $this->actingAs($owner)->putJson('/api/panel/account/owner-whatsapp', [
            'owner_whatsapp' => '51911111111',
        ])->assertForbidden()->assertJsonPath('message', $message);

        $this->assertTrue(Hash::check('password', $owner->fresh()->password));
    }

    private function demoOwner(): User
    {
        $business = Business::factory()->create(['is_demo' => true]);

        return User::factory()->for($business)->create();
    }
}
