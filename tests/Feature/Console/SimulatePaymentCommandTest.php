<?php

namespace Tests\Feature\Console;

use App\Models\Business;
use App\Models\Device;
use App\Models\ReceivedPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulatePaymentCommandTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create(['timezone' => 'America/Lima']);
        Device::factory()->for($this->business)->create();
    }

    public function test_inserts_payment_with_exact_time(): void
    {
        $this->artisan('payguard:simulate-payment', [
            '--business' => $this->business->id,
            '--amount' => '50.00',
            '--payer' => 'Juan Perez',
            '--security-code' => '123',
            '--operation-code' => 'OP-1',
            '--at' => '2026-10-28 15:20',
        ])->assertSuccessful();

        $payment = ReceivedPayment::firstOrFail();

        $this->assertSame($this->business->id, $payment->business_id);
        $this->assertSame('yape', $payment->wallet);
        $this->assertSame('50.00', $payment->amount);
        $this->assertSame('Juan Perez', $payment->payer_name);
        $this->assertSame('123', $payment->security_code);
        $this->assertSame('OP-1', $payment->operation_code);
        $this->assertSame('2026-10-28 20:20:00', $payment->received_at->format('Y-m-d H:i:s'));
        $this->assertTrue($payment->parsed);
        $this->assertStringStartsWith('sim-', $payment->idempotency_key);
        $this->assertNotNull($payment->device->last_payment_at);
    }

    public function test_minutes_ago_sets_relative_time(): void
    {
        $this->artisan('payguard:simulate-payment', [
            '--business' => $this->business->id,
            '--amount' => '10',
            '--minutes-ago' => '5',
        ])->assertSuccessful();

        $receivedAt = ReceivedPayment::firstOrFail()->received_at;

        $this->assertLessThanOrEqual(120, abs($receivedAt->timestamp - now()->subMinutes(5)->timestamp));
    }

    public function test_rejects_invalid_options(): void
    {
        $this->artisan('payguard:simulate-payment', [
            '--business' => $this->business->id,
            '--amount' => '50.00',
            '--at' => '2026-10-28 15:20',
            '--minutes-ago' => '5',
        ])->assertFailed();

        $this->artisan('payguard:simulate-payment', [
            '--business' => $this->business->id,
            '--amount' => 'abc',
        ])->assertFailed();

        $this->artisan('payguard:simulate-payment', [
            '--business' => $this->business->id,
            '--amount' => '50.00',
            '--wallet' => 'nequi',
        ])->assertFailed();

        $this->artisan('payguard:simulate-payment', [
            '--business' => $this->business->id,
            '--amount' => '50.00',
            '--security-code' => '12',
        ])->assertFailed();

        $this->artisan('payguard:simulate-payment', [
            '--business' => $this->business->id,
            '--amount' => '50.00',
            '--at' => 'not a date',
        ])->assertFailed();

        $this->artisan('payguard:simulate-payment', ['--amount' => '50.00'])->assertFailed();

        $this->assertDatabaseCount('received_payments', 0);
    }

    public function test_requires_a_device(): void
    {
        $business = Business::factory()->create();

        $this->artisan('payguard:simulate-payment', [
            '--business' => $business->id,
            '--amount' => '50.00',
        ])->assertFailed();

        $this->assertDatabaseCount('received_payments', 0);
    }

    public function test_is_blocked_in_production_unless_enabled(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('payguard:simulate-payment', [
            '--business' => $this->business->id,
            '--amount' => '50.00',
        ])->assertFailed();

        $this->assertDatabaseCount('received_payments', 0);

        config()->set('payguard.simulator.enabled', true);

        $this->artisan('payguard:simulate-payment', [
            '--business' => $this->business->id,
            '--amount' => '50.00',
        ])->assertSuccessful();

        $this->assertDatabaseCount('received_payments', 1);
    }
}
