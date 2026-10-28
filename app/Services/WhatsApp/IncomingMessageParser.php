<?php

namespace App\Services\WhatsApp;

class IncomingMessageParser
{
    private const SUPPORTED = ['image', 'audio', 'text'];

    /**
     * @return array<int, array{wa_message_id: string, from: string, type: string, media_id: ?string, text: ?string}>
     */
    public function parse(array $payload): array
    {
        $messages = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                foreach ($value['messages'] ?? [] as $message) {
                    if (! isset($message['id'], $message['from'], $message['type'])) {
                        continue;
                    }

                    $rawType = $message['type'];

                    $messages[] = [
                        'wa_message_id' => $message['id'],
                        'from' => $message['from'],
                        'type' => in_array($rawType, self::SUPPORTED, true) ? $rawType : 'unsupported',
                        'media_id' => $message[$rawType]['id'] ?? null,
                        'text' => $message['text']['body'] ?? null,
                    ];
                }
            }
        }

        return $messages;
    }

    /**
     * @return array<int, array{wa_message_id: string, status: string, error: ?string}>
     */
    public function parseStatuses(array $payload): array
    {
        $updates = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                foreach ($value['statuses'] ?? [] as $status) {
                    if (! isset($status['id'], $status['status'])) {
                        continue;
                    }

                    $updates[] = [
                        'wa_message_id' => $status['id'],
                        'status' => $status['status'],
                        'error' => $status['errors'][0]['title'] ?? null,
                    ];
                }
            }
        }

        return $updates;
    }
}
