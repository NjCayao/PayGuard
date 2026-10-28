<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\ProcessIncomingMessage;
use App\Jobs\UpdateOwnerAlertStatus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payguard.whatsapp.verify_token', 'test-verify');
        config()->set('payguard.whatsapp.app_secret', 'test-secret');
    }

    public function test_verifies_webhook_with_valid_token(): void
    {
        $response = $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=test-verify&hub.challenge=abc123');

        $response->assertOk();
        $response->assertSee('abc123', false);
    }

    public function test_rejects_verification_with_wrong_token(): void
    {
        $response = $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=abc123');

        $response->assertForbidden();
    }

    public function test_rejects_post_with_invalid_signature(): void
    {
        Queue::fake();

        $response = $this->postJson(
            '/api/webhooks/whatsapp',
            ['object' => 'whatsapp_business_account'],
            ['X-Hub-Signature-256' => 'sha256=invalid']
        );

        $response->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_dispatches_job_for_incoming_message(): void
    {
        Queue::fake();

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'messages' => [[
                            'id' => 'wamid.test1',
                            'from' => '51982226835',
                            'type' => 'image',
                            'image' => ['id' => 'media123', 'mime_type' => 'image/jpeg'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $response = $this->postJson('/api/webhooks/whatsapp', $payload, [
            'X-Hub-Signature-256' => $this->signature($payload),
        ]);

        $response->assertOk();
        Queue::assertPushed(ProcessIncomingMessage::class, function ($job) {
            return $job->message['wa_message_id'] === 'wamid.test1'
                && $job->message['from'] === '51982226835'
                && $job->message['type'] === 'image';
        });
    }

    public function test_dispatches_job_for_status_updates(): void
    {
        Queue::fake();

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'statuses' => [[
                            'id' => 'wamid.test1',
                            'status' => 'delivered',
                        ]],
                    ],
                ]],
            ]],
        ];

        $response = $this->postJson('/api/webhooks/whatsapp', $payload, [
            'X-Hub-Signature-256' => $this->signature($payload),
        ]);

        $response->assertOk();
        Queue::assertPushed(UpdateOwnerAlertStatus::class, function ($job) {
            return $job->update['wa_message_id'] === 'wamid.test1'
                && $job->update['status'] === 'delivered';
        });
        Queue::assertNotPushed(ProcessIncomingMessage::class);
    }

    private function signature(array $payload): string
    {
        return 'sha256='.hash_hmac('sha256', json_encode($payload), 'test-secret');
    }
}
