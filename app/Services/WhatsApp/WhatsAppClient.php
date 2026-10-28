<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WhatsAppClient
{
    public function mediaMetadata(string $mediaId): array
    {
        return $this->request()->get("{$this->graphUrl()}/{$mediaId}")->throw()->json();
    }

    public function downloadMedia(string $url): string
    {
        return $this->request((int) config('payguard.whatsapp.media_timeout'))->get($url)->throw()->body();
    }

    public function sendText(string $to, string $body, ?string $replyTo = null): void
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body, 'preview_url' => false],
        ];

        if ($replyTo !== null) {
            $payload['context'] = ['message_id' => $replyTo];
        }

        $this->request()->post("{$this->phoneUrl()}/messages", $payload)->throw();
    }

    public function sendTemplate(string $to, array $textParams): void
    {
        $parameters = array_map(
            fn (string $text) => ['type' => 'text', 'text' => $text],
            array_values($textParams)
        );

        $this->request()->post("{$this->phoneUrl()}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => config('payguard.whatsapp.alert_template'),
                'language' => ['code' => config('payguard.whatsapp.alert_template_lang')],
                'components' => [
                    ['type' => 'body', 'parameters' => $parameters],
                ],
            ],
        ])->throw();
    }

    private function request(?int $timeout = null): PendingRequest
    {
        return Http::withToken((string) config('payguard.whatsapp.token'))
            ->connectTimeout((int) config('payguard.whatsapp.connect_timeout'))
            ->timeout($timeout ?? (int) config('payguard.whatsapp.timeout'))
            ->acceptJson();
    }

    private function graphUrl(): string
    {
        return 'https://graph.facebook.com/'.config('payguard.whatsapp.api_version');
    }

    private function phoneUrl(): string
    {
        return $this->graphUrl().'/'.config('payguard.whatsapp.phone_number_id');
    }
}
