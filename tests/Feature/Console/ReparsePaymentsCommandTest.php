<?php

namespace Tests\Feature\Console;

use App\Models\Business;
use App\Models\Device;
use App\Models\ReceivedPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReparsePaymentsCommandTest extends TestCase
{
    use RefreshDatabase;

    private const REAL_TEXT = "Confirmación de Pago\n".
        "Antoni Mon* te envió un pago por S/ 0.1. El cód. de seguridad es: 944\n".
        'Antoni Mon* te envió un pago por S/ 0.1. El cód. de seguridad es: 944';

    private function unparsedPayment(array $overrides = []): ReceivedPayment
    {
        return ReceivedPayment::factory()->create(array_merge([
            'amount' => null,
            'payer_name' => null,
            'security_code' => null,
            'operation_code' => null,
            'raw_text' => self::REAL_TEXT,
            'parsed' => false,
        ], $overrides));
    }

    public function test_reparses_real_notification(): void
    {
        $payment = $this->unparsedPayment();

        $this->artisan('payguard:reparse-payments')->assertSuccessful();

        $this->assertDatabaseHas('received_payments', [
            'id' => $payment->id,
            'amount' => '0.10',
            'payer_name' => 'Antoni Mon*',
            'security_code' => '944',
            'parsed' => true,
        ]);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $payment = $this->unparsedPayment();

        $this->artisan('payguard:reparse-payments', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('received_payments', [
            'id' => $payment->id,
            'amount' => null,
            'payer_name' => null,
            'security_code' => null,
            'parsed' => false,
        ]);
    }

    public function test_skips_simulator_rows(): void
    {
        $payment = $this->unparsedPayment(['source_package' => 'payguard.simulator']);

        $this->artisan('payguard:reparse-payments')->assertSuccessful();

        $this->assertSame(false, $payment->fresh()->parsed);
        $this->assertNull($payment->fresh()->amount);
    }

    public function test_leaves_truly_unreadable_rows(): void
    {
        $payment = $this->unparsedPayment(['raw_text' => 'Yape! Tienes novedades en tu cuenta']);

        $this->artisan('payguard:reparse-payments')->assertSuccessful();

        $this->assertSame(1, ReceivedPayment::count());
        $this->assertSame(false, $payment->fresh()->parsed);
        $this->assertNull($payment->fresh()->amount);
    }

    public function test_filters_by_business(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        $deviceA = Device::factory()->create(['business_id' => $businessA->id]);
        $deviceB = Device::factory()->create(['business_id' => $businessB->id]);

        $paymentA = $this->unparsedPayment(['business_id' => $businessA->id, 'device_id' => $deviceA->id]);
        $paymentB = $this->unparsedPayment(['business_id' => $businessB->id, 'device_id' => $deviceB->id]);

        $this->artisan('payguard:reparse-payments', ['--business' => $businessA->id])->assertSuccessful();

        $this->assertSame(true, $paymentA->fresh()->parsed);
        $this->assertSame(false, $paymentB->fresh()->parsed);
    }

    public function test_limit_processes_first_rows_only(): void
    {
        $first = $this->unparsedPayment();
        $second = $this->unparsedPayment();

        $this->artisan('payguard:reparse-payments', ['--limit' => 1])->assertSuccessful();

        $this->assertSame(true, $first->fresh()->parsed);
        $this->assertSame(false, $second->fresh()->parsed);
    }

    public function test_rejects_unknown_business_and_bad_limit(): void
    {
        $this->unparsedPayment();

        $this->artisan('payguard:reparse-payments', ['--business' => 999999])->assertFailed();
        $this->artisan('payguard:reparse-payments', ['--limit' => 0])->assertFailed();

        $this->assertSame(false, ReceivedPayment::first()->parsed);
    }
}
