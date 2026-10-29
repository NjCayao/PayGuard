<?php

namespace App\Jobs;

use App\Models\Verification;
use App\Services\Meta\ScreenshotInvalidException;
use App\Services\Meta\ScreenshotReader;
use App\Services\Verification\VerificationService;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeScreenshotVerification implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public array $backoff;

    public function __construct(public int $verificationId)
    {
        $this->tries = max(1, (int) config('payguard.screenshot.tries', 2));
        $this->backoff = array_fill(0, $this->tries - 1, max(1, (int) config('payguard.screenshot.backoff_seconds', 5)));
    }

    public function handle(ScreenshotReader $reader, VerificationService $service): void
    {
        $verification = Verification::find($this->verificationId);

        if ($verification === null || $verification->status !== 'processing') {
            return;
        }

        if ($verification->input_type !== 'image') {
            return;
        }

        $lock = Cache::lock("analyze:{$verification->id}", 120);

        if (! $lock->get()) {
            $this->release(5);

            return;
        }

        try {
            if ($verification->extracted === null) {
                try {
                    $reader->read($verification)->applyTo($verification);
                } catch (ScreenshotInvalidException $e) {
                    $lock->release();

                    $this->failed($e);

                    return;
                }
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
                'reason' => 'No se pudo leer la captura.',
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
            Log::error('Failed to report a failed screenshot analysis.', [
                'verification_id' => $this->verificationId,
                'error' => $inner->getMessage(),
            ]);
        }

        Log::error('Screenshot analysis failed.', [
            'verification_id' => $this->verificationId,
            'error' => $exception->getMessage(),
        ]);
    }
}
