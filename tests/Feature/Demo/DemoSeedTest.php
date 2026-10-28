<?php

namespace Tests\Feature\Demo;

use App\Models\Business;
use App\Models\OwnerAlert;
use App\Models\ReceivedPayment;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payguard.demo.email', 'demo@example.com');
        config()->set('payguard.demo.password', 'demo-secret-1');
        config()->set('payguard.demo.enabled', false);
        Storage::fake('local');
    }

    public function test_seed_creates_the_full_demo_account(): void
    {
        Http::fake();
        config()->set('payguard.whatsapp.token', 'test-token');

        $this->artisan('payguard:demo-seed')->assertSuccessful();

        $business = Business::where('is_demo', true)->firstOrFail();

        $this->assertSame('Bodega El Sol', $business->name);
        $this->assertSame('51999888001', $business->owner_whatsapp);
        $this->assertNotNull($business->yape_holder_name);
        $this->assertSame(2, $business->cashiers()->count());
        $this->assertSame(1, $business->devices()->count());

        foreach ($business->cashiers as $cashier) {
            $this->assertStringStartsWith('51999888', $cashier->whatsapp);
        }

        $owner = User::where('business_id', $business->id)->firstOrFail();
        $this->assertSame('demo@example.com', $owner->email);
        $this->assertTrue(Hash::check('demo-secret-1', $owner->password));

        $this->assertSame(12, ReceivedPayment::forBusiness($business->id)->count());
        $this->assertSame(13, Verification::forBusiness($business->id)->count());
        $this->assertSame(3, OwnerAlert::forBusiness($business->id)->count());

        $this->assertSame(
            ['already_confirmed', 'mismatch_amount', 'mismatch_code', 'mismatch_recipient', 'multiple_matches', 'not_found', 'offline', 'pending', 'reused', 'stale', 'verified'],
            Verification::forBusiness($business->id)->distinct()->pluck('reason_code')->sort()->values()->all()
        );

        $this->assertSame(
            ['audio', 'image', 'text'],
            Verification::forBusiness($business->id)->distinct()->pluck('input_type')->sort()->values()->all()
        );

        $this->assertSame(3, Verification::forBusiness($business->id)->where('status', 'verified')->count());
        $this->assertSame(1, Verification::forBusiness($business->id)->where('status', 'pending')->count());

        $reused = Verification::forBusiness($business->id)->where('wa_message_id', 'wamid.demo.v8')->firstOrFail();
        $original = Verification::forBusiness($business->id)->where('wa_message_id', 'wamid.demo.v1')->firstOrFail();
        $this->assertSame($original->media_sha256, $reused->media_sha256);
        $this->assertNotNull($original->received_payment_id);
        $this->assertNull($reused->received_payment_id);

        foreach (Verification::forBusiness($business->id)->where('input_type', 'image')->get() as $verification) {
            $this->assertNotNull($verification->media_path);
            $this->assertTrue(Storage::disk('local')->exists($verification->media_path));
        }

        foreach (ReceivedPayment::forBusiness($business->id)->get() as $payment) {
            $this->assertGreaterThan(now()->subDays(2), $payment->received_at);
        }

        Http::assertNothingSent();
    }

    public function test_seed_is_idempotent(): void
    {
        $this->artisan('payguard:demo-seed')->assertSuccessful();
        $this->artisan('payguard:demo-seed')->assertSuccessful();

        $this->assertSame(1, Business::where('is_demo', true)->count());
        $this->assertSame(12, ReceivedPayment::count());
        $this->assertSame(13, Verification::count());
        $this->assertSame(3, OwnerAlert::count());
    }

    public function test_reset_rebuilds_after_changes(): void
    {
        $this->artisan('payguard:demo-seed')->assertSuccessful();

        $business = Business::where('is_demo', true)->firstOrFail();
        $business->update(['name' => 'Destrozado']);
        ReceivedPayment::destroy(ReceivedPayment::forBusiness($business->id)->take(5)->pluck('id'));

        $this->artisan('payguard:demo-reset')->assertSuccessful();

        $business->refresh();

        $this->assertSame('Bodega El Sol', $business->name);
        $this->assertSame(12, ReceivedPayment::forBusiness($business->id)->count());
        $this->assertSame(13, Verification::forBusiness($business->id)->count());
        $this->assertSame(3, OwnerAlert::forBusiness($business->id)->count());
    }

    public function test_seed_does_not_touch_real_businesses(): void
    {
        $real = Business::factory()->create();
        $payment = ReceivedPayment::factory()->create(['business_id' => $real->id]);

        $this->artisan('payguard:demo-seed')->assertSuccessful();

        $this->assertDatabaseHas('received_payments', ['id' => $payment->id]);
        $this->assertFalse($real->fresh()->isDemo());
    }

    public function test_commands_are_blocked_in_production_unless_enabled(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('payguard:demo-seed')->assertFailed();
        $this->artisan('payguard:demo-reset')->assertFailed();
        $this->assertSame(0, Business::where('is_demo', true)->count());

        config()->set('payguard.demo.enabled', true);

        $this->artisan('payguard:demo-seed')->assertSuccessful();
        $this->assertSame(1, Business::where('is_demo', true)->count());
    }

    public function test_commands_require_credentials_from_env(): void
    {
        config()->set('payguard.demo.email', null);

        $this->artisan('payguard:demo-seed')->assertFailed();
        $this->assertSame(0, Business::where('is_demo', true)->count());

        config()->set('payguard.demo.email', 'demo@example.com');
        config()->set('payguard.demo.password', '');

        $this->artisan('payguard:demo-reset')->assertFailed();
        $this->assertSame(0, Business::where('is_demo', true)->count());

        config()->set('payguard.demo.password', 'demo-secret-1');
        config()->set('payguard.demo.email', 'not-an-email');

        $this->artisan('payguard:demo-seed')->assertFailed();
        $this->assertSame(0, Business::where('is_demo', true)->count());
    }
}
