<?php

namespace App\Console\Commands;

use App\Models\Verification;
use App\Services\Verification\CashierReplies;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpireStuckVerifications extends Command
{
    protected $signature = 'verifications:expire-stuck
        {--dry-run : Show how many verifications would expire without changing them}';

    protected $description = 'Close image verifications stuck in processing for too long';

    public function handle(CashierReplies $replies, WhatsAppClient $whatsapp): int
    {
        $stuck = Verification::where('input_type', 'image')
            ->where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes((int) config('payguard.verification.processing_timeout_minutes')))
            ->with('cashier')
            ->get();

        if ($this->option('dry-run')) {
            $this->info("{$stuck->count()} stuck verification(s).");

            return self::SUCCESS;
        }

        $rendered = $replies->renderExpired();
        $notifySince = now()->subHours((int) config('payguard.verification.notify_hours'));
        $closed = 0;
        $notified = 0;

        foreach ($stuck as $verification) {
            $verification->update(['status' => 'error', 'reason' => $rendered['reason'], 'reason_code' => 'expired']);
            $closed++;

            if ($verification->created_at->lt($notifySince) || $verification->cashier === null) {
                continue;
            }

            try {
                $whatsapp->sendText(
                    $verification->cashier->whatsapp,
                    $rendered['reply'],
                    $verification->wa_message_id
                );
                $notified++;
            } catch (Throwable $e) {
                Log::warning('Could not notify an expired verification.', [
                    'verification_id' => $verification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Closed {$closed} stuck verification(s), notified {$notified}.");

        return self::SUCCESS;
    }
}
