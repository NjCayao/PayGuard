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

class OfflineVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Cashier $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payguard.whatsapp.token', 'test-token');
        config()->set('payguard.whatsapp.phone_number_id', '12345');
        Queue::fake([RecheckPendingVerification::class]);

        $this->business = Business::factory()->create(['yape_holder_name' => 'Maria Torres']);
        $this->cashier = Cashier::factory()->for($this->business)->create();
        Device::factory()->for($this->business)->create(['last_seen_at' => now()->subMinutes(30)]);
    }

    public function test_pending_entry_replies_offline_and_alerts_owner(): void
    {
        $this->fakeWhatsApp();

        $verification = $this->processingVerification();

        app(VerificationService::class)->analyze($verification, allowPending: true);

        $verification->refresh();

        $this->assertSame('pending', $verification->status);
        $this->assertSame('offline', $verification->reason_code);
        $this->assertSame(
            'El celular del negocio no está conectado. No puedo confirmar este pago.',
            $verification->reason
        );
        $this->assertNotNull($verification->recheck_until);
        $this->assertTextReply($verification, '⚠ El celular del negocio no está conectado.');
        $this->assertTemplateSent();
        $this->assertDatabaseHas('owner_alerts', [
            'verification_id' => $verification->id,
            'status' => 'sent',
        ]);
        Queue::assertPushed(RecheckPendingVerification::class);
    }

    public function test_expired_recheck_finalizes_as_offline_not_found(): void
    {
        $this->fakeWhatsApp();

        $verification = $this->processingVerification([
            'status' => 'pending',
            'recheck_until' => now()->subSecond(),
        ]);

        app(VerificationService::class)->analyze($verification, allowPending: false);

        $verification->refresh();

        $this->assertSame('not_found', $verification->status);
        $this->assertSame('offline', $verification->reason_code);
        $this->assertTextReply($verification, '⚠ El celular del negocio no está conectado.');
        $this->assertTemplateSent();
    }

    public function test_offline_alert_is_sent_only_once_per_cooldown(): void
    {
        $this->fakeWhatsApp();

        $first = $this->processingVerification();
        $second = $this->processingVerification();

        app(VerificationService::class)->analyze($first, allowPending: true);
        app(VerificationService::class)->analyze($second, allowPending: true);

        Http::assertSentCount(3);
    }

    public function test_online_business_keeps_normal_pending_reply(): void
    {
        $this->fakeWhatsApp();

        $this->business->devices()->update(['last_seen_at' => now()]);

        $verification = $this->processingVerification();

        app(VerificationService::class)->analyze($verification, allowPending: true);

        $verification->refresh();

        $this->assertSame('pending', $verification->status);
        $this->assertSame('pending', $verification->reason_code);
        $this->assertTextReply($verification, '⏳ Todavía no llega el pago de S/ 50.00.');
        Http::assertNotSent(fn ($request) => ($request->data()['type'] ?? '') === 'template');
    }

    public function test_verifies_normally_when_device_is_back(): void
    {
        $this->fakeWhatsApp();

        $this->business->devices()->update(['last_seen_at' => now()]);

        $device = $this->business->devices()->first();

        ReceivedPayment::factory()->for($this->business)->for($device)->create([
            'amount' => '50.00',
            'wallet' => 'yape',
            'payer_name' => 'Juan Perez',
            'received_at' => now()->subMinute(),
        ]);

        $verification = $this->processingVerification();

        app(VerificationService::class)->analyze($verification, allowPending: true);

        $this->assertSame('verified', $verification->fresh()->status);
    }

    private function processingVerification(array $overrides = []): Verification
    {
        return Verification::factory()
            ->for($this->business)->for($this->cashier)
            ->analyzed()
            ->create(array_merge([
                'input_type' => 'image',
                'status' => 'processing',
                'amount' => '50.00',
                'recipient_name' => 'Maria Torres',
                'paid_at' => now()->subMinute(),
            ], $overrides));
    }

    private function fakeWhatsApp(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
        ]);
    }

    private function assertTextReply(Verification $verification, string $startsWith): void
    {
        Http::assertSent(function ($request) use ($startsWith) {
            $data = $request->data();

            return ($data['type'] ?? '') === 'text'
                && $data['to'] === $this->cashier->whatsapp
                && str_starts_with($data['text']['body'], $startsWith);
        });
    }

    private function assertTemplateSent(): void
    {
        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['type'] ?? '') === 'template'
                && $data['to'] === $this->business->owner_whatsapp;
        });
    }
}
