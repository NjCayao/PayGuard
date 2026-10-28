<?php

namespace Tests\Feature\Verification;

use App\Models\Business;
use App\Models\Cashier;
use App\Models\Device;
use App\Models\ReceivedPayment;
use App\Models\Verification;
use App\Services\Verification\PaymentMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMatcherTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Cashier $cashier;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create(['yape_holder_name' => 'Maria Torres']);
        $this->cashier = Cashier::factory()->for($this->business)->create();
        $this->device = Device::factory()->for($this->business)->create();
    }

    public function test_exact_match_is_verified(): void
    {
        $verification = $this->verification(['security_code' => '123']);
        $payment = $this->payment(['security_code' => '123']);

        $outcome = app(PaymentMatcher::class)->match($verification);

        $this->assertSame('verified', $outcome->status);
        $this->assertSame('verified', $outcome->reasonCode);
        $this->assertSame($payment->id, $outcome->receivedPaymentId);
        $this->assertFalse($outcome->holderSkipped);
    }

    public function test_match_without_codes_is_verified(): void
    {
        $verification = $this->verification();
        $payment = $this->payment();

        $outcome = app(PaymentMatcher::class)->match($verification);

        $this->assertSame('verified', $outcome->status);
        $this->assertSame($payment->id, $outcome->receivedPaymentId);
    }

    public function test_match_ignores_name_case_accents_and_spaces(): void
    {
        $verification = $this->verification(['recipient_name' => '  MARÍA   torres ']);
        $this->payment();

        $this->assertSame('verified', app(PaymentMatcher::class)->match($verification)->status);
    }

    public function test_different_security_code_is_mismatch(): void
    {
        $verification = $this->verification(['security_code' => '123']);
        $this->payment(['security_code' => '999']);

        $outcome = app(PaymentMatcher::class)->match($verification);

        $this->assertSame('mismatch', $outcome->status);
        $this->assertSame('mismatch_code', $outcome->reasonCode);
    }

    public function test_one_sided_security_code_still_verifies(): void
    {
        $verification = $this->verification(['security_code' => '123']);
        $this->payment(['security_code' => null]);

        $this->assertSame('verified', app(PaymentMatcher::class)->match($verification)->status);
    }

    public function test_different_recipient_is_mismatch(): void
    {
        $verification = $this->verification(['recipient_name' => 'Pedro Lopez']);
        $this->payment();

        $outcome = app(PaymentMatcher::class)->match($verification);

        $this->assertSame('mismatch', $outcome->status);
        $this->assertSame('mismatch_recipient', $outcome->reasonCode);
    }

    public function test_missing_holder_skips_recipient_check(): void
    {
        $this->business->update(['yape_holder_name' => null]);

        $verification = $this->verification(['recipient_name' => 'Pedro Lopez']);
        $this->payment();

        $outcome = app(PaymentMatcher::class)->match($verification);

        $this->assertSame('verified', $outcome->status);
        $this->assertTrue($outcome->holderSkipped);
    }

    public function test_unreadable_recipient_skips_name_check(): void
    {
        $verification = $this->verification(['recipient_name' => null]);
        $this->payment();

        $this->assertSame('verified', app(PaymentMatcher::class)->match($verification)->status);
    }

    public function test_different_operation_code_discards_candidate(): void
    {
        $verification = $this->verification(['operation_code' => 'OP-1']);
        $this->payment(['operation_code' => 'OP-2']);

        $this->assertSame('retry', app(PaymentMatcher::class)->match($verification)->status);
    }

    public function test_matching_operation_code_verifies(): void
    {
        $verification = $this->verification(['operation_code' => 'OP-1']);
        $this->payment(['operation_code' => 'OP-1']);

        $this->assertSame('verified', app(PaymentMatcher::class)->match($verification)->status);
    }

    public function test_nearest_payment_wins(): void
    {
        $verification = $this->verification();
        $this->payment(['received_at' => now()->subMinutes(8)]);
        $nearest = $this->payment(['received_at' => now()->subMinutes(4)]);

        $outcome = app(PaymentMatcher::class)->match($verification);

        $this->assertSame('verified', $outcome->status);
        $this->assertSame($nearest->id, $outcome->receivedPaymentId);
    }

    public function test_claimed_payment_reports_reused(): void
    {
        $verification = $this->verification();
        $payment = $this->payment();

        Verification::factory()
            ->for($this->business)->for($this->cashier)
            ->create(['status' => 'verified', 'received_payment_id' => $payment->id]);

        $outcome = app(PaymentMatcher::class)->match($verification);

        $this->assertSame('reused', $outcome->status);
        $this->assertNull($outcome->receivedPaymentId);
    }

    public function test_unclaimed_payment_is_preferred_over_claimed(): void
    {
        $verification = $this->verification();
        $claimed = $this->payment(['received_at' => now()->subMinutes(4)]);
        $fresh = $this->payment(['received_at' => now()->subMinutes(6)]);

        Verification::factory()
            ->for($this->business)->for($this->cashier)
            ->create(['status' => 'verified', 'received_payment_id' => $claimed->id]);

        $outcome = app(PaymentMatcher::class)->match($verification);

        $this->assertSame('verified', $outcome->status);
        $this->assertSame($fresh->id, $outcome->receivedPaymentId);
    }

    public function test_different_amount_is_mismatch_with_amounts(): void
    {
        $verification = $this->verification(['amount' => '50.00']);
        $this->payment(['amount' => '10.00']);

        $outcome = app(PaymentMatcher::class)->match($verification);

        $this->assertSame('mismatch', $outcome->status);
        $this->assertSame('mismatch_amount', $outcome->reasonCode);
        $this->assertSame(['amount' => '10.00', 'expected' => '50.00'], $outcome->params);
    }

    public function test_no_candidate_and_recent_is_retry(): void
    {
        $verification = $this->verification();

        $outcome = app(PaymentMatcher::class)->match($verification);

        $this->assertSame('retry', $outcome->status);
    }

    public function test_no_candidate_and_stale_is_not_found(): void
    {
        $verification = $this->verification(['paid_at' => now()->subMinutes(30)]);

        $outcome = app(PaymentMatcher::class)->match($verification);

        $this->assertSame('not_found', $outcome->status);
        $this->assertSame('stale', $outcome->reasonCode);
    }

    public function test_ignores_other_business_payments(): void
    {
        $verification = $this->verification();

        ReceivedPayment::factory()->create([
            'amount' => '50.00',
            'wallet' => 'yape',
            'received_at' => now()->subMinutes(5),
        ]);

        $this->assertSame('retry', app(PaymentMatcher::class)->match($verification)->status);
    }

    public function test_ignores_other_wallet_and_outside_window(): void
    {
        $verification = $this->verification();
        $this->payment(['wallet' => 'plin']);
        $this->payment(['received_at' => now()->subMinutes(30)]);

        $this->assertSame('retry', app(PaymentMatcher::class)->match($verification)->status);
    }

    public function test_unparsed_payment_can_confirm(): void
    {
        $verification = $this->verification();
        $payment = $this->payment(['parsed' => false, 'payer_name' => null]);

        $outcome = app(PaymentMatcher::class)->match($verification);

        $this->assertSame('verified', $outcome->status);
        $this->assertSame($payment->id, $outcome->receivedPaymentId);
    }

    private function verification(array $overrides = []): Verification
    {
        return Verification::factory()
            ->for($this->business)->for($this->cashier)
            ->analyzed()
            ->create(array_merge([
                'amount' => '50.00',
                'paid_at' => now()->subMinutes(5),
                'recipient_name' => 'Maria Torres',
            ], $overrides));
    }

    private function payment(array $overrides = []): ReceivedPayment
    {
        return ReceivedPayment::factory()
            ->for($this->business)->for($this->device)
            ->create(array_merge([
                'amount' => '50.00',
                'wallet' => 'yape',
                'received_at' => now()->subMinutes(5),
            ], $overrides));
    }
}
