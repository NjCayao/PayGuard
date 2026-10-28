<?php

namespace Tests\Feature\Verification;

use App\Jobs\RecheckPendingVerification;
use App\Models\Business;
use App\Models\Cashier;
use App\Models\Device;
use App\Models\ReceivedPayment;
use App\Models\Verification;
use App\Services\Verification\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecheckPendingVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Cashier $cashier;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payguard.whatsapp.token', 'test-token');
        config()->set('payguard.whatsapp.phone_number_id', '12345');
        Queue::fake([RecheckPendingVerification::class]);

        $this->business = Business::factory()->create(['yape_holder_name' => 'Maria Torres']);
        $this->cashier = Cashier::factory()->for($this->business)->create();
        $this->device = Device::factory()->for($this->business)->online()->create();
    }

    public function test_verifies_when_payment_arrives_in_window(): void
    {
        $this->fakeWhatsApp();

        $verification = $this->pendingVerification();

        ReceivedPayment::factory()->for($this->business)->for($this->device)->create([
            'amount' => '50.00',
            'wallet' => 'yape',
            'payer_name' => 'Juan Perez',
            'received_at' => now()->subMinute(),
        ]);

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('verified', $verification->status);
        $this->assertNotNull($verification->received_payment_id);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['type'] ?? '') === 'text'
                && str_starts_with($data['text']['body'], '✅ Yape recibido: S/ 50.00 de Juan Perez');
        });
    }

    public function test_stays_pending_and_redispatches_within_window(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
        ]);

        $verification = $this->pendingVerification();

        $this->runJob($verification);

        $this->assertSame('pending', $verification->fresh()->status);
        Http::assertNothingSent();
        Queue::assertPushed(RecheckPendingVerification::class, function ($job) use ($verification) {
            return $job->verificationId === $verification->id;
        });
    }

    public function test_closes_as_not_found_when_window_expires(): void
    {
        $this->fakeWhatsApp();

        $verification = $this->pendingVerification(['recheck_until' => now()->subSecond()]);

        $this->runJob($verification);

        $verification->refresh();

        $this->assertSame('not_found', $verification->status);
        $this->assertSame('No llegó ningún pago de S/ 50.00.', $verification->reason);
        Queue::assertNotPushed(RecheckPendingVerification::class);

        Http::assertSent(function ($request) use ($verification) {
            $data = $request->data();

            return ($data['type'] ?? '') === 'text'
                && ($data['context']['message_id'] ?? '') === $verification->wa_message_id
                && str_starts_with($data['text']['body'], '❌ No llegó ningún pago de S/ 50.00.');
        });
    }

    public function test_skips_non_pending_verifications(): void
    {
        Http::fake();

        $verification = Verification::factory()->for($this->business)->for($this->cashier)->create([
            'status' => 'verified',
        ]);

        $this->runJob($verification);

        Http::assertNothingSent();
        Queue::assertNotPushed(RecheckPendingVerification::class);
    }

    private function pendingVerification(array $overrides = []): Verification
    {
        return Verification::factory()
            ->for($this->business)->for($this->cashier)
            ->analyzed()
            ->create(array_merge([
                'status' => 'pending',
                'amount' => '50.00',
                'recipient_name' => 'Maria Torres',
                'paid_at' => now()->subMinute(),
                'recheck_until' => now()->addMinutes(2),
            ], $overrides));
    }

    private function fakeWhatsApp(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
        ]);
    }

    private function runJob(Verification $verification): void
    {
        (new RecheckPendingVerification($verification->id))->handle(app(VerificationService::class));
    }
}
