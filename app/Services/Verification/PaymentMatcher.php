<?php

namespace App\Services\Verification;

use App\Models\ReceivedPayment;
use App\Models\Verification;
use Illuminate\Support\Collection;

class PaymentMatcher
{
    public function match(Verification $verification): VerificationOutcome
    {
        $business = $verification->business;
        $paidAt = $verification->paid_at ?? $verification->created_at;
        $window = (int) $business->match_window_minutes;

        $pool = $this->pool($verification, $paidAt, $window, claimed: false);

        $sameAmount = $pool->filter(
            fn (ReceivedPayment $payment) => bccomp((string) $payment->amount, (string) $verification->amount, 2) === 0
        );

        $recipientUnchecked = blank($business->yape_holder_name) || blank($verification->recipient_name);
        $codeMismatch = false;
        $recipientMismatch = false;

        $holder = $business->yape_holder_name;
        $checkRecipient = ! blank($holder) && ! blank($verification->recipient_name);

        $survivors = $sameAmount->filter(function (ReceivedPayment $payment) use ($verification, $holder, $checkRecipient, &$codeMismatch, &$recipientMismatch) {
            if ($this->codesDiffer($verification->security_code, $payment->security_code)) {
                $codeMismatch = true;

                return false;
            }

            if ($this->codesDiffer($verification->operation_code, $payment->operation_code)) {
                return false;
            }

            $codeMatch = $this->codesMatch($verification->security_code, $payment->security_code);

            if (! $codeMatch && $checkRecipient && ! RecipientMatcher::matches($verification->recipient_name, $holder)) {
                $recipientMismatch = true;

                return false;
            }

            return true;
        });

        if ($survivors->isNotEmpty()) {
            $winner = $this->nearest($survivors, $paidAt);

            return new VerificationOutcome(
                status: 'verified',
                reasonCode: 'verified',
                receivedPaymentId: $winner->id,
                holderSkipped: $recipientUnchecked,
            );
        }

        $claimedWinner = $this->claimedWinner($verification, $paidAt, $window, $checkRecipient);

        if ($claimedWinner !== null) {
            return new VerificationOutcome(
                status: 'reused',
                reasonCode: 'reused',
                holderSkipped: $recipientUnchecked,
            );
        }

        if ($codeMismatch) {
            return new VerificationOutcome('mismatch', 'mismatch_code', holderSkipped: $recipientUnchecked);
        }

        if ($recipientMismatch) {
            return new VerificationOutcome('mismatch', 'mismatch_recipient', holderSkipped: $recipientUnchecked);
        }

        $otherAmount = $pool->reject(
            fn (ReceivedPayment $payment) => bccomp((string) $payment->amount, (string) $verification->amount, 2) === 0
        );

        if ($otherAmount->isNotEmpty()) {
            $nearest = $this->nearest($otherAmount, $paidAt);

            return new VerificationOutcome('mismatch', 'mismatch_amount', [
                'amount' => (string) $nearest->amount,
                'expected' => (string) $verification->amount,
            ], holderSkipped: $recipientUnchecked);
        }

        if ($paidAt->lt(now()->subMinutes((int) config('payguard.verification.recent_minutes')))) {
            return new VerificationOutcome('not_found', 'stale');
        }

        return new VerificationOutcome('retry', 'pending');
    }

    private function pool(Verification $verification, $paidAt, int $window, bool $claimed): Collection
    {
        return ReceivedPayment::forBusiness($verification->business_id)
            ->where('wallet', $verification->wallet)
            ->whereNotNull('amount')
            ->when(
                $verification->trial_key !== null,
                fn ($query) => $query->where('trial_key', $verification->trial_key),
                fn ($query) => $query->whereNull('trial_key'),
            )
            ->whereBetween('received_at', [
                $paidAt->copy()->subMinutes($window),
                $paidAt->copy()->addMinutes($window),
            ])
            ->when($claimed,
                fn ($query) => $query->whereHas('verification'),
                fn ($query) => $query->whereDoesntHave('verification'),
            )
            ->orderBy('received_at')
            ->get();
    }

    private function claimedWinner(Verification $verification, $paidAt, int $window, bool $checkRecipient): ?ReceivedPayment
    {
        $candidates = $this->pool($verification, $paidAt, $window, claimed: true)->filter(
            fn (ReceivedPayment $payment) => bccomp((string) $payment->amount, (string) $verification->amount, 2) === 0
                && ! $this->codesDiffer($verification->security_code, $payment->security_code)
                && ! $this->codesDiffer($verification->operation_code, $payment->operation_code)
                && ($this->codesMatch($verification->security_code, $payment->security_code)
                    || ! $checkRecipient
                    || RecipientMatcher::matches($verification->recipient_name, $verification->business->yape_holder_name))
        );

        return $candidates->isEmpty() ? null : $this->nearest($candidates, $paidAt);
    }

    private function codesDiffer(?string $screenshot, ?string $payment): bool
    {
        return $screenshot !== null && $screenshot !== ''
            && $payment !== null && $payment !== ''
            && $screenshot !== $payment;
    }

    private function codesMatch(?string $screenshot, ?string $payment): bool
    {
        return $screenshot !== null && $screenshot !== ''
            && $screenshot === $payment;
    }

    private function nearest(Collection $payments, $paidAt): ReceivedPayment
    {
        return $payments->sortBy(fn (ReceivedPayment $payment) => abs($payment->received_at->timestamp - $paidAt->timestamp))->first();
    }
}
