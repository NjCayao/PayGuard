<?php

namespace Tests\Feature\Verification;

use App\Models\Business;
use App\Models\Cashier;
use App\Models\Verification;
use App\Services\Verification\DuplicateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DuplicateCheckerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Cashier $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();
        $this->cashier = Cashier::factory()->for($this->business)->create();
    }

    public function test_same_operation_code_is_duplicate(): void
    {
        $previous = $this->verification(['operation_code' => 'OP-9', 'status' => 'verified']);
        $current = $this->verification(['operation_code' => 'OP-9']);

        $this->assertSame($previous->id, app(DuplicateChecker::class)->findPrevious($current)?->id);
    }

    public function test_same_code_amount_and_minute_is_duplicate(): void
    {
        $previous = $this->verification([
            'security_code' => '123',
            'amount' => '50.00',
            'paid_at' => '2026-10-28 20:20:01',
            'status' => 'verified',
        ]);
        $current = $this->verification([
            'security_code' => '123',
            'amount' => '50.00',
            'paid_at' => '2026-10-28 20:20:59',
        ]);

        $this->assertSame($previous->id, app(DuplicateChecker::class)->findPrevious($current)?->id);
    }

    public function test_different_minute_is_not_duplicate(): void
    {
        $this->verification([
            'security_code' => '123',
            'amount' => '50.00',
            'paid_at' => '2026-10-28 20:20:59',
            'status' => 'verified',
        ]);
        $current = $this->verification([
            'security_code' => '123',
            'amount' => '50.00',
            'paid_at' => '2026-10-28 20:21:00',
        ]);

        $this->assertNull(app(DuplicateChecker::class)->findPrevious($current));
    }

    public function test_same_sha_is_duplicate(): void
    {
        $sha = hash('sha256', 'same-bytes');
        $previous = $this->verification(['media_sha256' => $sha, 'status' => 'verified']);
        $current = $this->verification(['media_sha256' => $sha]);

        $this->assertSame($previous->id, app(DuplicateChecker::class)->findPrevious($current)?->id);
    }

    public function test_error_verifications_do_not_count(): void
    {
        $this->verification(['operation_code' => 'OP-9', 'status' => 'error']);
        $current = $this->verification(['operation_code' => 'OP-9']);

        $this->assertNull(app(DuplicateChecker::class)->findPrevious($current));
    }

    public function test_ignores_itself_other_businesses_and_missing_signals(): void
    {
        $current = $this->verification(['operation_code' => 'OP-9']);

        $this->assertNull(app(DuplicateChecker::class)->findPrevious($current));

        Verification::factory()->create(['operation_code' => 'OP-9', 'status' => 'verified']);

        $this->assertNull(app(DuplicateChecker::class)->findPrevious($current));

        $signalless = $this->verification([
            'operation_code' => null,
            'media_sha256' => null,
            'security_code' => null,
        ]);

        $this->assertNull(app(DuplicateChecker::class)->findPrevious($signalless));
    }

    public function test_returns_earliest_previous(): void
    {
        $first = $this->verification(['operation_code' => 'OP-9', 'status' => 'pending']);
        $this->verification(['operation_code' => 'OP-9', 'status' => 'pending']);
        $current = $this->verification(['operation_code' => 'OP-9']);

        $this->assertSame($first->id, app(DuplicateChecker::class)->findPrevious($current)?->id);
    }

    public function test_whatsapp_verification_ignores_trial_duplicates(): void
    {
        $this->verification([
            'operation_code' => 'OP-9',
            'status' => 'verified',
            'source' => 'trial',
            'trial_key' => (string) Str::uuid(),
        ]);
        $current = $this->verification(['operation_code' => 'OP-9']);

        $this->assertNull(app(DuplicateChecker::class)->findPrevious($current));
    }

    public function test_trial_verification_only_matches_its_own_key(): void
    {
        $key = (string) Str::uuid();

        $this->verification(['operation_code' => 'OP-9', 'status' => 'verified']);
        $this->verification([
            'operation_code' => 'OP-9',
            'status' => 'verified',
            'source' => 'trial',
            'trial_key' => (string) Str::uuid(),
        ]);
        $current = $this->verification(['operation_code' => 'OP-9', 'source' => 'trial', 'trial_key' => $key]);

        $this->assertNull(app(DuplicateChecker::class)->findPrevious($current));

        $previous = $this->verification([
            'operation_code' => 'OP-9',
            'status' => 'verified',
            'source' => 'trial',
            'trial_key' => $key,
        ]);

        $this->assertSame($previous->id, app(DuplicateChecker::class)->findPrevious($current)?->id);
    }

    private function verification(array $overrides = []): Verification
    {
        return Verification::factory()
            ->for($this->business)->for($this->cashier)
            ->analyzed()
            ->create($overrides);
    }
}
