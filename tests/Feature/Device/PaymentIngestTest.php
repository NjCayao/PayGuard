<?php

namespace Tests\Feature\Device;

use App\Models\Device;
use App\Models\ReceivedPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentIngestTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'package' => 'com.bcp.innovacxion.yapeapp',
            'title' => 'Yape!',
            'text' => 'JUAN PEREZ te envió un pago por S/ 50',
            'big_text' => null,
            'posted_at_ms' => Carbon::parse('2026-10-27 15:30:00', 'America/Lima')->valueOf(),
            'idempotency_key' => 'key-1',
            ...$overrides,
        ];
    }

    private function tokenFor(User $owner): string
    {
        $device = Device::factory()->create(['business_id' => $owner->business_id]);

        return $device->createToken('android-app')->plainTextToken;
    }

    public function test_stores_received_payment(): void
    {
        $owner = User::factory()->create();

        $response = $this->withToken($this->tokenFor($owner))
            ->postJson('/api/device/payments', $this->payload());

        $response->assertCreated();
        $response->assertJsonPath('data.parsed', true);

        $this->assertDatabaseHas('received_payments', [
            'business_id' => $owner->business_id,
            'wallet' => 'yape',
            'source_package' => 'com.bcp.innovacxion.yapeapp',
            'amount' => '50.00',
            'payer_name' => 'JUAN PEREZ',
            'received_at' => '2026-10-27 20:30:00',
            'parsed' => true,
            'idempotency_key' => 'key-1',
        ]);

        $payment = ReceivedPayment::where('idempotency_key', 'key-1')->firstOrFail();
        $this->assertStringContainsString('JUAN PEREZ te envió un pago por S/ 50', $payment->raw_text);
        $this->assertNotNull($payment->device->last_payment_at);
    }

    public function test_payment_arrives_in_token_business(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->withToken($this->tokenFor($owner))
            ->postJson('/api/device/payments', $this->payload())
            ->assertCreated();

        $this->assertSame(1, ReceivedPayment::forBusiness($owner->business_id)->count());
        $this->assertSame(0, ReceivedPayment::forBusiness($other->business_id)->count());
    }

    public function test_resending_same_key_does_not_duplicate(): void
    {
        $owner = User::factory()->create();
        $token = $this->tokenFor($owner);

        $this->withToken($token)->postJson('/api/device/payments', $this->payload())->assertCreated();

        $response = $this->withToken($token)
            ->postJson('/api/device/payments', $this->payload());

        $response->assertOk();
        $response->assertJsonPath('data.duplicate', true);
        $this->assertSame(1, ReceivedPayment::count());
    }

    public function test_foreign_idempotency_key_returns_no_details(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        ReceivedPayment::factory()->create([
            'business_id' => $other->business_id,
            'device_id' => Device::factory()->create(['business_id' => $other->business_id])->id,
            'idempotency_key' => 'key-1',
        ]);

        $response = $this->withToken($this->tokenFor($owner))
            ->postJson('/api/device/payments', $this->payload());

        $response->assertOk();
        $response->assertJsonPath('data.duplicate', true);
        $response->assertJsonPath('data.id', null);
        $this->assertSame(0, ReceivedPayment::forBusiness($owner->business_id)->count());
    }

    public function test_unknown_package_is_not_saved(): void
    {
        $owner = User::factory()->create();

        $response = $this->withToken($this->tokenFor($owner))
            ->postJson('/api/device/payments', $this->payload([
                'package' => 'com.example.other',
            ]));

        $response->assertOk();
        $response->assertJsonPath('data.saved', false);
        $this->assertSame(0, ReceivedPayment::count());
    }

    public function test_outgoing_transfer_is_not_saved(): void
    {
        $owner = User::factory()->create();

        $response = $this->withToken($this->tokenFor($owner))
            ->postJson('/api/device/payments', $this->payload([
                'text' => 'Yapeaste S/ 20 a MARIA LOPEZ',
            ]));

        $response->assertOk();
        $response->assertJsonPath('data.saved', false);
        $this->assertSame(0, ReceivedPayment::count());
    }

    public function test_unreadable_payment_is_saved_as_not_parsed(): void
    {
        $owner = User::factory()->create();

        $response = $this->withToken($this->tokenFor($owner))
            ->postJson('/api/device/payments', $this->payload([
                'text' => 'Recibiste un pago por Yape, revísalo en la app',
            ]));

        $response->assertCreated();
        $response->assertJsonPath('data.parsed', false);

        $this->assertDatabaseHas('received_payments', [
            'idempotency_key' => 'key-1',
            'parsed' => false,
            'amount' => null,
        ]);
    }

    public function test_stores_security_code_when_present(): void
    {
        $owner = User::factory()->create();

        $this->withToken($this->tokenFor($owner))
            ->postJson('/api/device/payments', $this->payload([
                'text' => 'JUAN te envió un pago por S/ 20. Código de seguridad: 789',
            ]))
            ->assertCreated();

        $this->assertDatabaseHas('received_payments', [
            'idempotency_key' => 'key-1',
            'security_code' => '789',
        ]);
    }

    public function test_revoked_token_cannot_save(): void
    {
        $owner = User::factory()->create();
        $device = Device::factory()->create(['business_id' => $owner->business_id]);
        $token = $device->createToken('android-app')->plainTextToken;
        $device->tokens()->delete();

        $this->withToken($token)
            ->postJson('/api/device/payments', $this->payload())
            ->assertUnauthorized();

        $this->assertSame(0, ReceivedPayment::count());
    }
}
