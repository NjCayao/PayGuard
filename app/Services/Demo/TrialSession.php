<?php

namespace App\Services\Demo;

use App\Models\Business;
use App\Models\ReceivedPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TrialSession
{
    public const HEADER = 'X-Trial-Key';

    public function resolveKey(Request $request): string
    {
        $key = $request->header(self::HEADER) ?? $request->query('trial_key');

        abort_unless(is_string($key) && Str::isUuid($key), 422, 'Falta una clave de prueba válida.');

        return $key;
    }

    /**
     * @return Collection<int, ReceivedPayment>
     */
    public function ensurePayments(Business $business, string $trialKey): Collection
    {
        $existing = ReceivedPayment::forBusiness($business->id)
            ->where('trial_key', $trialKey)
            ->orderBy('id')
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $device = $business->devices()->orderBy('id')->first();

        abort_if($device === null, 500, 'La cuenta demo no tiene dispositivo.');

        $prefix = 'TRIAL-'.strtoupper(substr($trialKey, 0, 8));
        $number = 0;

        foreach (config('payguard.demo.trial.payments', []) as $spec) {
            $number++;

            $raw = "Yape! {$spec['payer_name']} te envió un pago por S/ {$spec['amount']}.";

            if (($spec['security_code'] ?? null) !== null) {
                $raw .= " Cód. de seguridad: {$spec['security_code']}.";
            }

            ReceivedPayment::create([
                'business_id' => $business->id,
                'device_id' => $device->id,
                'wallet' => 'yape',
                'source_package' => (string) array_key_first(config('payguard.notifications.packages', [])),
                'amount' => $spec['amount'],
                'payer_name' => $spec['payer_name'],
                'security_code' => $spec['security_code'] ?? null,
                'operation_code' => "{$prefix}-{$number}",
                'received_at' => now(),
                'raw_text' => $raw,
                'parsed' => true,
                'idempotency_key' => "trial-{$trialKey}-{$number}",
                'trial_key' => $trialKey,
            ]);
        }

        return ReceivedPayment::forBusiness($business->id)
            ->where('trial_key', $trialKey)
            ->orderBy('id')
            ->get();
    }

    public function refresh(ReceivedPayment $payment): void
    {
        $payment->update(['received_at' => now()]);
    }
}
