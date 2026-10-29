<?php

namespace App\Services\Verification;

use App\Jobs\RecheckPendingVerification;
use App\Models\Business;
use App\Models\ReceivedPayment;
use App\Models\Verification;
use App\Services\Device\DevicePresence;
use App\Services\WhatsApp\OwnerAlerter;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class VerificationService
{
    public function __construct(
        private PaymentMatcher $matcher,
        private TextQueryMatcher $queryMatcher,
        private DuplicateChecker $duplicates,
        private CashierReplies $replies,
        private WhatsAppClient $whatsapp,
        private OwnerAlerter $alerter,
        private DevicePresence $presence,
    ) {}

    public function analyze(Verification $verification, bool $allowPending): void
    {
        $verification->refresh();

        if (! in_array($verification->status, ['processing', 'pending'], true)) {
            return;
        }

        $business = $verification->business;
        $cashier = $verification->cashier;

        if ($business === null || $cashier === null) {
            return;
        }

        if ($verification->input_type !== 'image') {
            $this->analyzeQuery($verification, $allowPending);

            return;
        }

        if ($verification->amount === null) {
            $this->finalize($verification, new VerificationOutcome('not_found', 'unreadable'));

            return;
        }

        $previous = $this->duplicates->findPrevious($verification);

        if ($previous !== null) {
            $this->handleDuplicate($verification, $previous);

            return;
        }

        $outcome = $this->matcher->match($verification);

        if ($outcome->status === 'retry') {
            if ($allowPending) {
                $this->markPending($verification);
            } else {
                $this->finalize($verification, $this->offline($business)
                    ? new VerificationOutcome('not_found', 'offline')
                    : new VerificationOutcome('not_found', 'not_found'));
            }

            return;
        }

        if ($outcome->status === 'verified') {
            $this->claim($verification, $outcome);

            return;
        }

        $this->finalize($verification, $outcome);
    }

    private function analyzeQuery(Verification $verification, bool $allowPending): void
    {
        if ($verification->amount === null) {
            $this->analyzeAmountlessQuery($verification);

            return;
        }

        $outcome = $this->queryMatcher->match($verification);

        if ($outcome->status === 'retry') {
            if ($allowPending) {
                $this->markPending($verification);
            } else {
                $this->finalize($verification, $this->offline($verification->business)
                    ? new VerificationOutcome('not_found', 'offline')
                    : new VerificationOutcome('not_found', 'not_found'));
            }

            return;
        }

        if ($outcome->status === 'verified') {
            $this->claim($verification, $outcome);

            return;
        }

        $this->finalize($verification, $outcome);
    }

    private function analyzeAmountlessQuery(Verification $verification): void
    {
        $outcome = $this->queryMatcher->matchWithoutAmount($verification);

        if ($outcome->status === 'verified') {
            $this->claim($verification, $outcome);

            return;
        }

        $this->finalize($verification, $outcome);
    }

    private function handleDuplicate(Verification $verification, Verification $previous): void
    {
        if ($previous->status === 'verified') {
            $this->finalize($verification, new VerificationOutcome('reused', 'reused'));

            return;
        }

        if (in_array($previous->status, ['processing', 'pending'], true)) {
            $business = $verification->business;

            $verification->update([
                'status' => 'pending',
                'recheck_until' => $verification->recheck_until
                    ?? $previous->recheck_until
                    ?? now()->addMinutes($business->recheck_minutes),
            ]);

            $rendered = $this->replies->render($verification, new VerificationOutcome('pending', 'already_reviewing'));

            $verification->update(['reason' => $rendered['reason'], 'reason_code' => 'already_reviewing']);

            $this->sendReply($verification, $rendered['reply']);

            $this->dispatchRecheck($verification);

            return;
        }

        $this->finalize($verification, new VerificationOutcome(
            status: $previous->status,
            reasonCode: $previous->reason_code ?? 'copied',
            literalReason: $previous->reason ?? '',
        ));
    }

    private function markPending(Verification $verification): void
    {
        $wasProcessing = $verification->status === 'processing';
        $offline = $this->offline($verification->business);

        $verification->update([
            'status' => 'pending',
            'recheck_until' => $verification->recheck_until
                ?? now()->addMinutes($verification->business->recheck_minutes),
        ]);

        if ($wasProcessing) {
            $rendered = $this->replies->render($verification, $offline
                ? new VerificationOutcome('pending', 'offline')
                : new VerificationOutcome('retry', 'pending'));

            $verification->update([
                'reason' => $rendered['reason'],
                'reason_code' => $offline ? 'offline' : 'pending',
            ]);

            $this->sendReply($verification, $rendered['reply']);

            if ($offline) {
                $this->alerter->sendOfflineAlert($verification);
            }
        }

        $this->dispatchRecheck($verification);
    }

    private function claim(Verification $verification, VerificationOutcome $outcome): void
    {
        try {
            $claimed = DB::transaction(function () use ($verification, $outcome) {
                $payment = ReceivedPayment::whereKey($outcome->receivedPaymentId)->lockForUpdate()->first();

                if ($payment === null || $this->isClaimed($payment)) {
                    return false;
                }

                $verification->update(['received_payment_id' => $payment->id]);

                return true;
            });
        } catch (QueryException $e) {
            $claimed = $e->getCode() === '23000' ? false : throw $e;
        }

        if (! $claimed) {
            $this->finalize($verification, new VerificationOutcome('reused', 'reused'));

            return;
        }

        $this->finalize($verification, $outcome);
    }

    private function finalize(Verification $verification, VerificationOutcome $outcome): void
    {
        $payment = $outcome->receivedPaymentId !== null
            ? ReceivedPayment::find($outcome->receivedPaymentId)
            : null;

        $rendered = $this->replies->render($verification, $outcome, $payment);

        $verification->update([
            'status' => $outcome->status,
            'reason' => $rendered['reason'],
            'reason_code' => $outcome->reasonCode,
            'received_payment_id' => $outcome->receivedPaymentId,
        ]);

        $this->sendReply($verification, $rendered['reply']);

        if ($outcome->reasonCode === 'offline') {
            $this->alerter->sendOfflineAlert($verification);
        } elseif ($this->shouldAlert($verification, $outcome->status)) {
            $this->alerter->sendSuspiciousAlert($verification, $rendered['reason']);
        }
    }

    private function shouldAlert(Verification $verification, string $status): bool
    {
        if ($verification->input_type !== 'image') {
            return false;
        }

        if (in_array($status, ['mismatch', 'reused'], true)) {
            return true;
        }

        if ($status === 'not_found') {
            return $verification->edit_signals !== null && $verification->edit_signals !== [];
        }

        return false;
    }

    private function offline(Business $business): bool
    {
        return ! $this->presence->online($business);
    }

    private function isClaimed(ReceivedPayment $payment): bool
    {
        return $payment->verification()->exists();
    }

    private function sendReply(Verification $verification, string $reply): void
    {
        // The demo account never sends real WhatsApp messages.
        if ($verification->business->isDemo()) {
            return;
        }

        $this->whatsapp->sendText(
            $verification->cashier->whatsapp,
            $reply,
            $verification->wa_message_id
        );
    }

    private function dispatchRecheck(Verification $verification): void
    {
        RecheckPendingVerification::dispatch($verification->id)
            ->delay(now()->addSeconds((int) config('payguard.verification.recheck_interval_seconds')));
    }
}
