<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsApp\IncomingMessageParser;
use PHPUnit\Framework\TestCase;

class IncomingMessageParserTest extends TestCase
{
    public function test_parses_supported_messages(): void
    {
        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [
                            [
                                'id' => 'wamid.img',
                                'from' => '51982226835',
                                'type' => 'image',
                                'image' => ['id' => 'media1', 'mime_type' => 'image/jpeg'],
                            ],
                            [
                                'id' => 'wamid.aud',
                                'from' => '51982226835',
                                'type' => 'audio',
                                'audio' => ['id' => 'media2', 'mime_type' => 'audio/ogg'],
                            ],
                            [
                                'id' => 'wamid.txt',
                                'from' => '51982226835',
                                'type' => 'text',
                                'text' => ['body' => 'Llego un Yape de 50?'],
                            ],
                        ],
                    ],
                ]],
            ]],
        ];

        $messages = app(IncomingMessageParser::class)->parse($payload);

        $this->assertCount(3, $messages);
        $this->assertSame('image', $messages[0]['type']);
        $this->assertSame('media1', $messages[0]['media_id']);
        $this->assertSame('audio', $messages[1]['type']);
        $this->assertSame('media2', $messages[1]['media_id']);
        $this->assertSame('text', $messages[2]['type']);
        $this->assertSame('Llego un Yape de 50?', $messages[2]['text']);
    }

    public function test_marks_unsupported_types(): void
    {
        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'id' => 'wamid.vid',
                            'from' => '51982226835',
                            'type' => 'video',
                            'video' => ['id' => 'media9'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $messages = app(IncomingMessageParser::class)->parse($payload);

        $this->assertCount(1, $messages);
        $this->assertSame('unsupported', $messages[0]['type']);
    }

    public function test_skips_statuses_and_malformed_entries(): void
    {
        $payload = [
            'entry' => [
                ['changes' => [['value' => ['statuses' => [['id' => 'wamid.x']]]]]],
                ['changes' => [['value' => ['messages' => [['type' => 'text']]]]]],
                ['no_changes' => true],
            ],
        ];

        $this->assertSame([], app(IncomingMessageParser::class)->parse($payload));
    }

    public function test_parses_status_updates(): void
    {
        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [
                            [
                                'id' => 'wamid.a1',
                                'status' => 'delivered',
                                'timestamp' => '1720000000',
                                'recipient_id' => '51982226835',
                            ],
                            [
                                'id' => 'wamid.a2',
                                'status' => 'failed',
                                'errors' => [['title' => 'Template does not exist']],
                            ],
                            ['status' => 'read'],
                        ],
                    ],
                ]],
            ]],
        ];

        $updates = app(IncomingMessageParser::class)->parseStatuses($payload);

        $this->assertCount(2, $updates);
        $this->assertSame('wamid.a1', $updates[0]['wa_message_id']);
        $this->assertSame('delivered', $updates[0]['status']);
        $this->assertNull($updates[0]['error']);
        $this->assertSame('wamid.a2', $updates[1]['wa_message_id']);
        $this->assertSame('failed', $updates[1]['status']);
        $this->assertSame('Template does not exist', $updates[1]['error']);
    }
}
