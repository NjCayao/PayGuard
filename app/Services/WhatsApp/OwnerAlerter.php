<?php

namespace App\Services\WhatsApp;

use App\Models\Business;
use Illuminate\Support\Facades\Log;
use Throwable;

class OwnerAlerter
{
    public function __construct(private WhatsAppClient $client) {}

    public function sendSuspiciousAlert(Business $business, string $cashierName, string $amount, string $reason): bool
    {
        try {
            $this->client->sendTemplate($business->owner_whatsapp, [
                $business->name,
                $cashierName,
                $amount,
                $reason,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::warning('Owner alert template failed, the alert stays in the panel.', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
