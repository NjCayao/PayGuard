<?php

namespace App\Services\Meta;

use App\Models\Verification;
use Illuminate\Support\Facades\Storage;

class ScreenshotReader
{
    private const MIMES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function __construct(private MetaModelClient $client) {}

    public function read(Verification $verification): ScreenshotData
    {
        $business = $verification->business;

        if ($business === null) {
            throw new MetaModelException('Verification has no business.');
        }

        $disk = Storage::disk(config('payguard.whatsapp.media_disk'));

        if ($verification->media_path === null || ! $disk->exists($verification->media_path)) {
            throw new MetaModelException('Screenshot file is missing.');
        }

        $mime = self::MIMES[strtolower(pathinfo($verification->media_path, PATHINFO_EXTENSION))] ?? null;

        if ($mime === null) {
            throw new MetaModelException('Unsupported screenshot type.');
        }

        $bytes = $disk->get($verification->media_path);

        $today = now($business->timezone)->toDateString();

        $start = microtime(true);

        $decoded = $this->client->complete(
            [
                ['role' => 'system', 'content' => 'You read Peruvian digital-wallet payment screenshots and return only JSON.'],
                ['role' => 'user', 'content' => [
                    ['type' => 'text', 'text' => $this->prompt($today)],
                    ['type' => 'image_url', 'image_url' => [
                        'url' => "data:{$mime};base64,".base64_encode($bytes),
                    ]],
                ]],
            ],
            ['type' => 'json_schema', 'json_schema' => [
                'name' => 'payment_screenshot',
                'schema' => $this->schema(),
            ]]
        );

        $ms = (int) round((microtime(true) - $start) * 1000);

        return ScreenshotData::fromModel($decoded, $business, $decoded, $ms);
    }

    private function prompt(string $today): string
    {
        return <<<PROMPT
            Read this Yape or Plin payment screenshot. Today is {$today} in Lima, Peru.
            Return a JSON object with:
            - wallet: "yape", "plin" or "unknown".
            - amount: the paid amount as a decimal string like "50.00", or null if unreadable.
            - recipient_name: the person or business that received the money, or null.
            - paid_date: the payment date as YYYY-MM-DD, or null. If the screenshot shows no year, assume the current year. If it shows no date at all, use null.
            - paid_time: the payment time as HH:MM in 24-hour format, or null.
            - security_code: the 3-digit Yape security code, or null.
            - operation_code: the operation number, or null.
            - edit_signals: a list of short strings describing anything that looks edited, inconsistent or suspicious in the image (blurry digits, mismatched fonts, misaligned boxes). Empty list if nothing looks wrong.
            Use null for any field you cannot read. Do not guess values.
            PROMPT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'wallet' => ['type' => 'string'],
                'amount' => ['type' => ['string', 'number', 'null']],
                'recipient_name' => ['type' => ['string', 'null']],
                'paid_date' => ['type' => ['string', 'null']],
                'paid_time' => ['type' => ['string', 'null']],
                'security_code' => ['type' => ['string', 'number', 'null']],
                'operation_code' => ['type' => ['string', 'number', 'null']],
                'edit_signals' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['wallet', 'amount', 'recipient_name', 'paid_date', 'paid_time', 'security_code', 'operation_code', 'edit_signals'],
            'additionalProperties' => false,
        ];
    }
}
