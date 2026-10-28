<?php

namespace App\Services\WhatsApp;

use App\Models\OwnerAlert;
use App\Models\Verification;
use App\Services\Demo\DemoProtection;
use App\Services\Verification\CashierReplies;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class OwnerAlerter
{
    public function __construct(private WhatsAppClient $client) {}

    public function sendSuspiciousAlert(Verification $verification, string $reason): OwnerAlert
    {
        return $this->send($verification, $reason);
    }

    public function sendOfflineAlert(Verification $verification): ?OwnerAlert
    {
        $key = "offline_alert:{$verification->business_id}";

        if (! Cache::add($key, true, now()->addMinutes(
            (int) config('payguard.device.offline_alert_cooldown_minutes')
        ))) {
            return null;
        }

        $alert = $this->send($verification, 'El celular del negocio no está conectado.');

        if ($alert->status === 'failed') {
            Cache::forget($key);
        }

        return $alert;
    }

    private function send(Verification $verification, string $reason): OwnerAlert
    {
        $business = $verification->business;

        $alert = OwnerAlert::create([
            'business_id' => $business->id,
            'verification_id' => $verification->id,
            'to' => $business->owner_whatsapp,
            'template' => (string) config('payguard.whatsapp.alert_template'),
            'reason' => mb_substr($reason, 0, 255),
            'status' => 'queued',
        ]);

        // The demo account registers the alert in the panel but never sends it.
        if ($business->isDemo()) {
            $alert->update(['status' => 'failed', 'error' => DemoProtection::message()]);

            return $alert;
        }

        try {
            $waMessageId = $this->client->sendTemplate($business->owner_whatsapp, [
                $business->name,
                $verification->cashier->name,
                CashierReplies::formatAmount($verification->amount),
                $reason,
            ]);

            $alert->update(['status' => 'sent', 'wa_message_id' => $waMessageId]);
        } catch (Throwable $e) {
            Log::warning('Owner alert template failed, the alert stays in the panel.', [
                'business_id' => $business->id,
                'owner_alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);

            $alert->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 255)]);
        }

        return $alert;
    }
}
