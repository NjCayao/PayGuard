<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\UpdateOwnerAlertStatus;
use App\Models\OwnerAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateOwnerAlertStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_status_by_wa_message_id(): void
    {
        $alert = OwnerAlert::factory()->create([
            'wa_message_id' => 'wamid.alert1',
            'status' => 'sent',
        ]);

        (new UpdateOwnerAlertStatus([
            'wa_message_id' => 'wamid.alert1',
            'status' => 'delivered',
            'error' => null,
        ]))->handle();

        $this->assertSame('delivered', $alert->fresh()->status);
    }

    public function test_failed_stores_error(): void
    {
        $alert = OwnerAlert::factory()->create([
            'wa_message_id' => 'wamid.alert2',
            'status' => 'sent',
        ]);

        (new UpdateOwnerAlertStatus([
            'wa_message_id' => 'wamid.alert2',
            'status' => 'failed',
            'error' => 'Template does not exist',
        ]))->handle();

        $alert->refresh();

        $this->assertSame('failed', $alert->status);
        $this->assertSame('Template does not exist', $alert->error);
    }

    public function test_ignores_unknown_messages_and_statuses(): void
    {
        $alert = OwnerAlert::factory()->create([
            'wa_message_id' => 'wamid.alert3',
            'status' => 'read',
        ]);

        (new UpdateOwnerAlertStatus([
            'wa_message_id' => 'wamid.unknown',
            'status' => 'delivered',
            'error' => null,
        ]))->handle();

        (new UpdateOwnerAlertStatus([
            'wa_message_id' => 'wamid.alert3',
            'status' => 'deleted',
            'error' => null,
        ]))->handle();

        (new UpdateOwnerAlertStatus([
            'wa_message_id' => 'wamid.alert3',
            'status' => 'delivered',
            'error' => null,
        ]))->handle();

        $this->assertSame('read', $alert->fresh()->status);
        $this->assertDatabaseCount('owner_alerts', 1);
    }
}
