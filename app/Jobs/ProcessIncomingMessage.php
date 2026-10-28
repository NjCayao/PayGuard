<?php

namespace App\Jobs;

use App\Models\Cashier;
use App\Models\Verification;
use App\Services\PhoneNumbers;
use App\Services\WhatsApp\MediaStore;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessIncomingMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 90];

    public function __construct(public array $message) {}

    public function handle(WhatsAppClient $client, MediaStore $media): void
    {
        $from = PhoneNumbers::normalize($this->message['from'] ?? '');
        $waMessageId = (string) ($this->message['wa_message_id'] ?? '');

        $cashier = $this->findCashier($from);

        if ($cashier === null) {
            $this->replyOnce(
                "wa_unknown:{$waMessageId}",
                $client,
                $from,
                $waMessageId,
                (string) config('payguard.whatsapp.replies.unauthorized')
            );

            return;
        }

        $type = $this->message['type'] ?? 'unsupported';

        if ($type === 'unsupported') {
            $this->replyOnce(
                "wa_unsupported:{$waMessageId}",
                $client,
                $from,
                $waMessageId,
                (string) config('payguard.whatsapp.replies.unsupported')
            );

            return;
        }

        try {
            $verification = Verification::create([
                'business_id' => $cashier->business_id,
                'cashier_id' => $cashier->id,
                'wa_message_id' => $waMessageId,
                'input_type' => $type,
                'status' => 'processing',
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return;
            }

            throw $e;
        }

        if ($type === 'text') {
            $verification->update(['extracted' => ['raw_text' => $this->message['text']]]);

            return;
        }

        $metadata = $client->mediaMetadata((string) ($this->message['media_id'] ?? ''));
        $bytes = $client->downloadMedia((string) ($metadata['url'] ?? ''));
        $media->store($verification, $bytes, (string) ($metadata['mime_type'] ?? ''));
    }

    public function failed(Throwable $exception): void
    {
        try {
            $from = PhoneNumbers::normalize($this->message['from'] ?? '');
            $waMessageId = (string) ($this->message['wa_message_id'] ?? '');

            if ($from === '' || $waMessageId === '') {
                return;
            }

            $verification = Verification::where('wa_message_id', $waMessageId)->first();

            if ($verification === null && $this->findCashier($from) === null) {
                $body = (string) config('payguard.whatsapp.replies.unauthorized');
            } else {
                $verification?->update([
                    'status' => 'error',
                    'reason' => 'No se pudo descargar o revisar el mensaje.',
                ]);
                $body = (string) config('payguard.whatsapp.replies.error');
            }

            app(WhatsAppClient::class)->sendText($from, $body, $waMessageId);
        } catch (Throwable $inner) {
            Log::error('Failed to report a failed WhatsApp verification.', [
                'error' => $inner->getMessage(),
            ]);
        }
    }

    private function findCashier(string $from): ?Cashier
    {
        if ($from === '') {
            return null;
        }

        return Cashier::where('whatsapp', $from)->where('is_active', true)->first();
    }

    private function replyOnce(string $key, WhatsAppClient $client, string $to, string $waMessageId, string $body): void
    {
        if (! Cache::add($key, true, now()->addHours((int) config('payguard.whatsapp.unknown_dedup_hours')))) {
            return;
        }

        try {
            $client->sendText($to, $body, $waMessageId);
        } catch (Throwable $e) {
            Cache::forget($key);

            throw $e;
        }
    }
}
