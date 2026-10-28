<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Business;
use App\Models\Cashier;
use App\Models\OwnerAlert;
use App\Models\Verification;
use App\Services\WhatsApp\OwnerAlerter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OwnerAlerterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payguard.whatsapp.token', 'test-token');
        config()->set('payguard.whatsapp.phone_number_id', '12345');
    }

    public function test_sends_template_with_ordered_params_and_registers_alert(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.alert1']]]),
        ]);

        $verification = $this->verification();

        $alert = app(OwnerAlerter::class)->sendSuspiciousAlert($verification, 'Monto distinto');

        Http::assertSent(function ($request) use ($verification) {
            $data = $request->data();
            $params = array_column($data['template']['components'][0]['parameters'], 'text');

            return $data['to'] === $verification->business->owner_whatsapp
                && $data['type'] === 'template'
                && $data['template']['name'] === 'alerta_pago_sospechoso'
                && $data['template']['language']['code'] === 'es'
                && $params === ['Bodega El Sol', 'Luis', 'S/ 50.00', 'Monto distinto'];
        });

        $this->assertSame('sent', $alert->status);
        $this->assertSame('wamid.alert1', $alert->wa_message_id);
        $this->assertSame($verification->id, $alert->verification_id);
        $this->assertSame('alerta_pago_sospechoso', $alert->template);
    }

    public function test_registers_failed_alert_when_template_send_fails(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $verification = $this->verification();

        $alert = app(OwnerAlerter::class)->sendSuspiciousAlert($verification, 'Monto distinto');

        $this->assertSame('failed', $alert->status);
        $this->assertNull($alert->wa_message_id);
        $this->assertNotNull($alert->error);
    }

    public function test_offline_alert_is_sent_only_once_per_cooldown(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.alert2']]]),
        ]);

        $verification = $this->verification();

        $first = app(OwnerAlerter::class)->sendOfflineAlert($verification);
        $second = app(OwnerAlerter::class)->sendOfflineAlert($verification);

        $this->assertNotNull($first);
        $this->assertSame('sent', $first->status);
        $this->assertNull($second);
        $this->assertSame(1, OwnerAlert::count());
        Http::assertSentCount(1);
    }

    private function verification(): Verification
    {
        $business = Business::factory()->create(['name' => 'Bodega El Sol']);
        $cashier = Cashier::factory()->for($business)->create(['name' => 'Luis']);

        return Verification::factory()->for($business)->for($cashier)->create(['amount' => '50.00']);
    }
}
