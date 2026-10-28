<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Business;
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

    public function test_sends_template_with_ordered_params(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.resp']]]),
        ]);

        $business = Business::factory()->create(['name' => 'Bodega El Sol']);

        $sent = app(OwnerAlerter::class)->sendSuspiciousAlert($business, 'Luis', 'S/ 50.00', 'Monto distinto');

        $this->assertTrue($sent);
        Http::assertSent(function ($request) use ($business) {
            $data = $request->data();
            $params = array_column($data['template']['components'][0]['parameters'], 'text');

            return $data['to'] === $business->owner_whatsapp
                && $data['type'] === 'template'
                && $data['template']['name'] === 'alerta_pago_sospechoso'
                && $data['template']['language']['code'] === 'es'
                && $params === ['Bodega El Sol', 'Luis', 'S/ 50.00', 'Monto distinto'];
        });
    }

    public function test_returns_false_when_template_send_fails(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $business = Business::factory()->create();

        $this->assertFalse(app(OwnerAlerter::class)->sendSuspiciousAlert($business, 'Luis', 'S/ 50.00', 'Monto distinto'));
    }
}
