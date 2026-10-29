<?php

namespace Tests\Feature\Verification;

use App\Models\Business;
use App\Models\Cashier;
use App\Models\Device;
use App\Models\ReceivedPayment;
use App\Models\Verification;
use App\Services\Payments\YapeNotificationParser;
use App\Services\Verification\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BcpPaymentTest extends TestCase
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

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
        ]);

        $this->business = Business::factory()->create(['yape_holder_name' => 'Nilson Jhonny Cayao Fernandez']);
        $this->cashier = Cashier::factory()->for($this->business)->create();
        $this->device = Device::factory()->for($this->business)->online()->create();
    }

    public function test_bcp_payment_verifies_and_operation_number_blocks_reuse(): void
    {
        $parsed = app(YapeNotificationParser::class)->parse(
            'Yape! MONTENEGRO SUXE JUAN CARLOS te envió un pago por S/ 1.00'
        );

        $this->assertNotNull($parsed);
        $this->assertSame('1.00', $parsed->amount);
        $this->assertSame('MONTENEGRO SUXE JUAN CARLOS', $parsed->payerName);
        $this->assertNull($parsed->securityCode);

        $payment = ReceivedPayment::factory()
            ->for($this->business)->for($this->device)
            ->create([
                'wallet' => 'yape',
                'amount' => $parsed->amount,
                'payer_name' => $parsed->payerName,
                'received_at' => now()->subMinutes(2),
            ]);

        $verification = $this->screenshotVerification([
            'amount' => '1.00',
            'recipient_name' => 'Nilson J Cayao F',
            'paid_at' => now()->subMinutes(2),
            'operation_code' => '06094813',
            'media_sha256' => hash('sha256', 'bcp-shot-1'),
        ]);

        app(VerificationService::class)->analyze($verification, allowPending: false);

        $verification->refresh();

        $this->assertSame('verified', $verification->status);
        $this->assertSame($payment->id, $verification->received_payment_id);
        $this->assertSame('Yape recibido: S/ 1.00 de MONTENEGRO SUXE JUAN CARLOS.', $verification->reason);

        $resent = $this->screenshotVerification([
            'amount' => '1.00',
            'recipient_name' => 'Nilson J Cayao F',
            'paid_at' => now()->subMinutes(2),
            'operation_code' => '06094813',
            'media_sha256' => hash('sha256', 'bcp-shot-2'),
        ]);

        app(VerificationService::class)->analyze($resent, allowPending: false);

        $this->assertSame('reused', $resent->fresh()->status);
        $this->assertSame('reused', $resent->fresh()->reason_code);
    }

    private function screenshotVerification(array $overrides): Verification
    {
        return Verification::factory()
            ->for($this->business)->for($this->cashier)
            ->create(array_merge([
                'input_type' => 'image',
                'status' => 'processing',
                'wallet' => 'yape',
            ], $overrides));
    }
}
