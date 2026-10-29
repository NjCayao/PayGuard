<?php

namespace Tests\Feature\Verification;

use App\Models\Business;
use App\Models\Cashier;
use App\Models\Device;
use App\Models\ReceivedPayment;
use App\Models\Verification;
use App\Services\Verification\TextQueryMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TextQueryMatcherTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Cashier $cashier;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();
        $this->cashier = Cashier::factory()->for($this->business)->create();
        $this->device = Device::factory()->for($this->business)->create();
    }

    public function test_single_match_is_verified(): void
    {
        $verification = $this->queryVerification();
        $payment = $this->payment();

        $outcome = app(TextQueryMatcher::class)->match($verification);

        $this->assertSame('verified', $outcome->status);
        $this->assertSame('verified', $outcome->reasonCode);
        $this->assertSame($payment->id, $outcome->receivedPaymentId);
    }

    public function test_name_prefix_filters_candidates(): void
    {
        $verification = $this->queryVerification(['payer_name' => 'Juan']);
        $this->payment(['payer_name' => 'Sonia Vit*']);
        $wanted = $this->payment(['payer_name' => 'Juan P*']);

        $outcome = app(TextQueryMatcher::class)->match($verification);

        $this->assertSame('verified', $outcome->status);
        $this->assertSame($wanted->id, $outcome->receivedPaymentId);
    }

    public function test_name_match_ignores_case_accents_and_asterisk(): void
    {
        $verification = $this->queryVerification(['payer_name' => 'josé']);
        $payment = $this->payment(['payer_name' => 'JOSE Q*']);

        $outcome = app(TextQueryMatcher::class)->match($verification);

        $this->assertSame('verified', $outcome->status);
        $this->assertSame($payment->id, $outcome->receivedPaymentId);
    }

    public function test_name_matches_second_token(): void
    {
        $verification = $this->queryVerification(['payer_name' => 'Mon']);
        $payment = $this->payment(['payer_name' => 'Antoni Mon*']);

        $outcome = app(TextQueryMatcher::class)->match($verification);

        $this->assertSame('verified', $outcome->status);
        $this->assertSame($payment->id, $outcome->receivedPaymentId);
    }

    public function test_unknown_name_is_retry(): void
    {
        $verification = $this->queryVerification(['payer_name' => 'Pedro']);
        $this->payment(['payer_name' => 'Juan P*']);

        $outcome = app(TextQueryMatcher::class)->match($verification);

        $this->assertSame('retry', $outcome->status);
    }

    public function test_several_matches_report_multiple_without_winner(): void
    {
        $verification = $this->queryVerification();
        $this->payment(['payer_name' => 'Antoni Mon*', 'received_at' => now()->subMinutes(4)]);
        $this->payment(['payer_name' => 'Sonia Vit*', 'received_at' => now()->subMinutes(3)]);

        $outcome = app(TextQueryMatcher::class)->match($verification);

        $this->assertSame('mismatch', $outcome->status);
        $this->assertSame('multiple_matches', $outcome->reasonCode);
        $this->assertNull($outcome->receivedPaymentId);
        $this->assertSame('2', $outcome->params['count']);
        $this->assertCount(2, $outcome->params['matches']);
    }

    public function test_claimed_payment_reports_already_confirmed(): void
    {
        $verification = $this->queryVerification();
        $payment = $this->payment();

        Verification::factory()
            ->for($this->business)->for($this->cashier)
            ->create(['status' => 'verified', 'received_payment_id' => $payment->id]);

        $outcome = app(TextQueryMatcher::class)->match($verification);

        $this->assertSame('reused', $outcome->status);
        $this->assertSame('already_confirmed', $outcome->reasonCode);
        $this->assertNull($outcome->receivedPaymentId);
        $this->assertSame($payment->received_at->toISOString(), $outcome->params['confirmed_at']);
    }

    public function test_unclaimed_payment_is_preferred_over_claimed(): void
    {
        $verification = $this->queryVerification();
        $claimed = $this->payment(['received_at' => now()->subMinutes(2)]);
        $fresh = $this->payment(['received_at' => now()->subMinutes(4)]);

        Verification::factory()
            ->for($this->business)->for($this->cashier)
            ->create(['status' => 'verified', 'received_payment_id' => $claimed->id]);

        $outcome = app(TextQueryMatcher::class)->match($verification);

        $this->assertSame('verified', $outcome->status);
        $this->assertSame($fresh->id, $outcome->receivedPaymentId);
    }

    public function test_no_candidate_is_retry(): void
    {
        $verification = $this->queryVerification();

        $this->assertSame('retry', app(TextQueryMatcher::class)->match($verification)->status);
    }

    public function test_matching_security_code_verifies(): void
    {
        $verification = $this->queryVerification(['security_code' => '944']);
        $payment = $this->payment(['security_code' => '944']);

        $outcome = app(TextQueryMatcher::class)->match($verification);

        $this->assertSame('verified', $outcome->status);
        $this->assertSame($payment->id, $outcome->receivedPaymentId);
    }

    public function test_payment_without_code_does_not_match_coded_query(): void
    {
        $verification = $this->queryVerification(['security_code' => '944']);
        $this->payment(['security_code' => null]);

        $outcome = app(TextQueryMatcher::class)->match($verification);

        $this->assertSame('mismatch', $outcome->status);
        $this->assertSame('mismatch_code', $outcome->reasonCode);
    }

    public function test_code_found_with_other_amount_is_mismatch_amount(): void
    {
        $verification = $this->queryVerification(['security_code' => '944']);
        $this->payment(['amount' => '10.00', 'security_code' => '944']);

        $outcome = app(TextQueryMatcher::class)->match($verification);

        $this->assertSame('mismatch', $outcome->status);
        $this->assertSame('mismatch_amount', $outcome->reasonCode);
        $this->assertSame('10.00', $outcome->params['amount']);
        $this->assertSame('50.00', $outcome->params['expected']);
    }

    public function test_unknown_code_is_not_found(): void
    {
        $verification = $this->queryVerification(['security_code' => '944']);
        $this->payment(['security_code' => '123']);

        $outcome = app(TextQueryMatcher::class)->match($verification);

        $this->assertSame('mismatch', $outcome->status);
        $this->assertSame('mismatch_code', $outcome->reasonCode);
    }

    public function test_code_without_any_payment_is_not_found(): void
    {
        $verification = $this->queryVerification(['security_code' => '944']);

        $outcome = app(TextQueryMatcher::class)->match($verification);

        $this->assertSame('not_found', $outcome->status);
        $this->assertSame('not_found', $outcome->reasonCode);
    }

    public function test_claimed_coded_payment_reports_already_confirmed(): void
    {
        $verification = $this->queryVerification(['security_code' => '944']);
        $payment = $this->payment(['security_code' => '944']);

        Verification::factory()
            ->for($this->business)->for($this->cashier)
            ->create(['status' => 'verified', 'received_payment_id' => $payment->id]);

        $outcome = app(TextQueryMatcher::class)->match($verification);

        $this->assertSame('reused', $outcome->status);
        $this->assertSame('already_confirmed', $outcome->reasonCode);
    }

    public function test_amountless_single_match_is_verified_and_claims(): void
    {
        $verification = $this->queryVerification(['amount' => null]);
        $payment = $this->payment(['received_at' => now()->subMinute()]);

        $outcome = app(TextQueryMatcher::class)->matchWithoutAmount($verification);

        $this->assertSame('verified', $outcome->status);
        $this->assertSame('amountless_single', $outcome->reasonCode);
        $this->assertSame($payment->id, $outcome->receivedPaymentId);
        $this->assertSame('50.00', $outcome->params['amount']);
    }

    public function test_amountless_several_matches_list_with_amounts(): void
    {
        $verification = $this->queryVerification(['amount' => null]);
        $this->payment(['amount' => '50.00', 'received_at' => now()->subMinutes(2)]);
        $this->payment(['amount' => '20.00', 'received_at' => now()->subMinute()]);

        $outcome = app(TextQueryMatcher::class)->matchWithoutAmount($verification);

        $this->assertSame('not_found', $outcome->status);
        $this->assertSame('amountless_multiple', $outcome->reasonCode);
        $this->assertNull($outcome->receivedPaymentId);
        $this->assertSame('2', $outcome->params['count']);
        $this->assertSame('20.00', $outcome->params['matches'][0]['amount']);
    }

    public function test_amountless_without_payments_asks_for_amount(): void
    {
        $verification = $this->queryVerification(['amount' => null]);

        $outcome = app(TextQueryMatcher::class)->matchWithoutAmount($verification);

        $this->assertSame('not_found', $outcome->status);
        $this->assertSame('ask_amount', $outcome->reasonCode);
    }

    public function test_amountless_ignores_payments_outside_its_window(): void
    {
        config()->set('payguard.query.no_amount_minutes', 3);

        $verification = $this->queryVerification(['amount' => null]);
        $this->payment(['received_at' => now()->subMinutes(5)]);

        $outcome = app(TextQueryMatcher::class)->matchWithoutAmount($verification);

        $this->assertSame('ask_amount', $outcome->reasonCode);
    }

    public function test_old_payments_are_outside_the_window(): void
    {
        config()->set('payguard.query.window_minutes', 10);

        $verification = $this->queryVerification();
        $this->payment(['received_at' => now()->subMinutes(11)]);

        $this->assertSame('retry', app(TextQueryMatcher::class)->match($verification)->status);
    }

    public function test_ignores_other_business_wallet_and_amount(): void
    {
        $verification = $this->queryVerification();

        ReceivedPayment::factory()->create([
            'amount' => '50.00',
            'wallet' => 'yape',
            'received_at' => now()->subMinutes(5),
        ]);
        $this->payment(['wallet' => 'plin']);
        $this->payment(['amount' => '10.00']);

        $this->assertSame('retry', app(TextQueryMatcher::class)->match($verification)->status);
    }

    public function test_whatsapp_query_ignores_trial_payments(): void
    {
        $verification = $this->queryVerification();
        $this->payment(['trial_key' => (string) Str::uuid()]);

        $this->assertSame('retry', app(TextQueryMatcher::class)->match($verification)->status);
    }

    public function test_trial_query_only_sees_its_own_key(): void
    {
        $key = (string) Str::uuid();

        $verification = $this->queryVerification(['source' => 'trial', 'trial_key' => $key]);
        $this->payment();
        $this->payment(['trial_key' => (string) Str::uuid()]);

        $this->assertSame('retry', app(TextQueryMatcher::class)->match($verification)->status);

        $own = $this->payment(['trial_key' => $key]);

        $outcome = app(TextQueryMatcher::class)->match($verification);

        $this->assertSame('verified', $outcome->status);
        $this->assertSame($own->id, $outcome->receivedPaymentId);
    }

    private function queryVerification(array $overrides = []): Verification
    {
        return Verification::factory()
            ->for($this->business)->for($this->cashier)
            ->create(array_merge([
                'input_type' => 'text',
                'status' => 'processing',
                'wallet' => 'yape',
                'amount' => '50.00',
                'extracted' => [
                    'raw_text' => 'yape de 50',
                    'query' => [
                        'is_payment_query' => true,
                        'wallet' => 'yape',
                        'amount' => '50.00',
                        'payer_name' => null,
                    ],
                ],
            ], $this->queryOverrides($overrides)));
    }

    private function queryOverrides(array $overrides): array
    {
        if (! array_key_exists('payer_name', $overrides)) {
            return $overrides;
        }

        return [
            'extracted' => [
                'raw_text' => 'yape de 50',
                'query' => [
                    'is_payment_query' => true,
                    'wallet' => 'yape',
                    'amount' => '50.00',
                    'payer_name' => $overrides['payer_name'],
                ],
            ],
        ];
    }

    private function payment(array $overrides = []): ReceivedPayment
    {
        return ReceivedPayment::factory()
            ->for($this->business)->for($this->device)
            ->create(array_merge([
                'amount' => '50.00',
                'wallet' => 'yape',
                'payer_name' => 'Juan Perez',
                'received_at' => now()->subMinutes(5),
            ], $overrides));
    }
}
