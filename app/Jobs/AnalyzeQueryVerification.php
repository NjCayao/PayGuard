<?php

namespace App\Jobs;

use App\Models\Verification;
use App\Services\Meta\PaymentQueryParser;
use App\Services\Meta\VoiceTranscriber;
use App\Services\Verification\VerificationService;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeQueryVerification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 90];

    public function __construct(public int $verificationId) {}

    public function handle(
        VoiceTranscriber $transcriber,
        PaymentQueryParser $parser,
        VerificationService $service,
    ): void {
        $verification = Verification::find($this->verificationId);

        if ($verification === null || $verification->status !== 'processing') {
            return;
        }

        if (! in_array($verification->input_type, ['audio', 'text'], true)) {
            return;
        }

        $lock = Cache::lock("analyze-query:{$verification->id}", 120);

        if (! $lock->get()) {
            $this->release(5);

            return;
        }

        try {
            if ($verification->input_type === 'audio' && $verification->transcript === null) {
                $result = $transcriber->transcribe($verification);

                $extracted = $verification->extracted ?? [];
                $extracted['asr'] = ['audio_duration_ms' => $result->audioDurationMs];

                $verification->update([
                    'transcript' => $result->text,
                    'extracted' => $extracted,
                    'ai_ms' => $result->aiMs,
                ]);
            }

            $verification->refresh();

            if (! array_key_exists('query', $verification->extracted ?? [])) {
                $text = $verification->input_type === 'audio'
                    ? $verification->transcript
                    : ($verification->extracted['raw_text'] ?? null);

                $parser->parse($text)->applyTo($verification);
            }

            $verification->refresh();

            $query = $verification->extracted['query'] ?? null;

            if (! is_array($query)
                || ($query['is_payment_query'] ?? false) !== true
                || ($query['amount'] ?? null) === null
            ) {
                $this->closeAsHelp($verification);

                return;
            }

            $service->analyze($verification, allowPending: true);
        } finally {
            $lock->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
            $verification = Verification::with(['business', 'cashier'])->find($this->verificationId);

            if ($verification === null || $verification->status !== 'processing') {
                return;
            }

            $verification->update([
                'status' => 'error',
                'reason' => 'No se pudo revisar tu consulta.',
                'reason_code' => 'error',
            ]);

            if ($verification->cashier !== null && ! $verification->business->isDemo()) {
                app(WhatsAppClient::class)->sendText(
                    $verification->cashier->whatsapp,
                    (string) config('payguard.whatsapp.replies.error'),
                    $verification->wa_message_id
                );
            }
        } catch (Throwable $inner) {
            Log::error('Failed to report a failed query analysis.', [
                'verification_id' => $this->verificationId,
                'error' => $inner->getMessage(),
            ]);
        }

        Log::error('Query analysis failed.', [
            'verification_id' => $this->verificationId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function closeAsHelp(Verification $verification): void
    {
        $label = $verification->input_type === 'audio' ? 'audio' : 'texto';

        $verification->update([
            'status' => 'error',
            'reason' => "Mensaje de {$label}: se envió ayuda.",
            'reason_code' => 'text_help',
        ]);

        if (! $verification->business->isDemo()) {
            app(WhatsAppClient::class)->sendText(
                $verification->cashier->whatsapp,
                (string) config('payguard.whatsapp.replies.text_help'),
                $verification->wa_message_id
            );
        }
    }
}
