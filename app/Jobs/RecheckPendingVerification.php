<?php

namespace App\Jobs;

use App\Models\Verification;
use App\Services\Verification\VerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecheckPendingVerification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(public int $verificationId) {}

    public function handle(VerificationService $service): void
    {
        $verification = Verification::find($this->verificationId);

        if ($verification === null || $verification->status !== 'pending') {
            return;
        }

        $service->analyze(
            $verification,
            allowPending: $verification->recheck_until !== null && now()->lt($verification->recheck_until)
        );
    }
}
