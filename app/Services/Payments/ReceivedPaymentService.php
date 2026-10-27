<?php

namespace App\Services\Payments;

use App\Models\Device;
use App\Models\ReceivedPayment;
use Carbon\Carbon;

class ReceivedPaymentService
{
    public function __construct(
        private NotificationParserResolver $resolver,
    ) {}

    public function store(Device $device, array $data): NotificationStoreResult
    {
        $existing = ReceivedPayment::where('idempotency_key', $data['idempotency_key'])->first();

        if ($existing !== null) {
            $payment = $existing->business_id === $device->business_id ? $existing : null;

            return new NotificationStoreResult(saved: true, duplicate: true, payment: $payment);
        }

        $parser = $this->resolver->forPackage($data['package']);

        if ($parser === null) {
            return new NotificationStoreResult(saved: false, duplicate: false, payment: null);
        }

        $text = implode("\n", array_filter([
            trim($data['title'] ?? ''),
            trim($data['text'] ?? ''),
            trim($data['big_text'] ?? ''),
        ]));

        $parsed = $parser->parse($text);

        if ($parsed === null) {
            return new NotificationStoreResult(saved: false, duplicate: false, payment: null);
        }

        $payment = ReceivedPayment::create([
            'business_id' => $device->business_id,
            'device_id' => $device->id,
            'wallet' => $parser->wallet(),
            'source_package' => $data['package'],
            'amount' => $parsed->amount,
            'payer_name' => $parsed->payerName,
            'security_code' => $parsed->securityCode,
            'operation_code' => $parsed->operationCode,
            'received_at' => Carbon::createFromTimestampMs($data['posted_at_ms'], 'UTC'),
            'raw_text' => $text,
            'parsed' => $parsed->isComplete(),
            'idempotency_key' => $data['idempotency_key'],
        ]);

        $device->update([
            'last_payment_at' => now(),
            'last_seen_at' => now(),
        ]);

        return new NotificationStoreResult(saved: true, duplicate: false, payment: $payment);
    }
}
