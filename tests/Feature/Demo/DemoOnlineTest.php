<?php

namespace Tests\Feature\Demo;

use App\Models\Business;
use App\Models\Cashier;
use App\Models\Device;
use App\Models\OwnerAlert;
use App\Models\ReceivedPayment;
use App\Models\User;
use App\Models\Verification;
use App\Services\Device\DevicePresence;
use App\Services\Verification\VerificationService;
use App\Services\WhatsApp\OwnerAlerter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemoOnlineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payguard.whatsapp.token', 'test-token');
        config()->set('payguard.whatsapp.phone_number_id', '12345');
    }

    public function test_demo_business_is_always_online(): void
    {
        $demo = Business::factory()->create(['is_demo' => true]);
        Device::factory()->for($demo)->create(['last_seen_at' => now()->subHour()]);

        $real = Business::factory()->create();
        Device::factory()->for($real)->create(['last_seen_at' => now()->subHour()]);

        $this->assertTrue(app(DevicePresence::class)->online($demo));
        $this->assertFalse(app(DevicePresence::class)->online($real));
    }

    public function test_demo_device_shows_connected_in_the_panel(): void
    {
        $business = Business::factory()->create(['is_demo' => true]);
        $owner = User::factory()->for($business)->create();
        $device = Device::factory()->for($business)->create(['last_seen_at' => now()->subHour()]);

        $response = $this->actingAs($owner)->getJson('/api/panel/devices');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $device->id);
        $response->assertJsonPath('data.0.is_online', true);
    }

    public function test_demo_verification_succeeds_without_heartbeat_and_sends_nothing(): void
    {
        Http::fake();

        $business = Business::factory()->create(['is_demo' => true, 'yape_holder_name' => 'Luz Marina Torres']);
        $cashier = Cashier::factory()->for($business)->create();
        Device::factory()->for($business)->create(['last_seen_at' => null]);

        $payment = ReceivedPayment::factory()->for($business)->create([
            'amount' => '50.00',
            'security_code' => '123',
            'received_at' => now(),
        ]);

        $verification = Verification::factory()->for($business)->for($cashier)->create([
            'input_type' => 'image',
            'wallet' => 'yape',
            'amount' => '50.00',
            'recipient_name' => 'Luz Marina Torres',
            'security_code' => '123',
            'paid_at' => now(),
            'status' => 'processing',
        ]);

        app(VerificationService::class)->analyze($verification, allowPending: true);

        $verification->refresh();

        $this->assertSame('verified', $verification->status);
        $this->assertSame($payment->id, $verification->received_payment_id);
        Http::assertNothingSent();
    }

    public function test_demo_mismatch_registers_a_failed_alert_without_sending(): void
    {
        Http::fake();

        $business = Business::factory()->create(['is_demo' => true]);
        $cashier = Cashier::factory()->for($business)->create();
        $verification = Verification::factory()->for($business)->for($cashier)->create(['amount' => '50.00']);

        $alert = app(OwnerAlerter::class)->sendSuspiciousAlert($verification, 'El código de seguridad no coincide.');

        Http::assertNothingSent();
        $this->assertSame('failed', $alert->status);
        $this->assertSame((string) config('payguard.demo.blocked_message'), $alert->error);
        $this->assertSame(1, OwnerAlert::forBusiness($business->id)->count());
    }

    public function test_offline_alert_is_also_skipped_for_demo(): void
    {
        Http::fake();

        $business = Business::factory()->create(['is_demo' => true]);
        $cashier = Cashier::factory()->for($business)->create();
        $verification = Verification::factory()->for($business)->for($cashier)->create();

        $alert = app(OwnerAlerter::class)->sendOfflineAlert($verification);

        Http::assertNothingSent();
        $this->assertNotNull($alert);
        $this->assertSame('failed', $alert->status);
    }
}
