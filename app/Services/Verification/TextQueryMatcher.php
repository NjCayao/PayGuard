<?php

namespace App\Services\Verification;

use App\Models\ReceivedPayment;
use App\Models\Verification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TextQueryMatcher
{
    public function match(Verification $verification): VerificationOutcome
    {
        $candidates = $this->candidates($verification, claimed: false);

        if ($candidates->count() === 1) {
            return new VerificationOutcome(
                status: 'verified',
                reasonCode: 'verified',
                receivedPaymentId: $candidates->first()->id,
            );
        }

        if ($candidates->count() > 1) {
            return new VerificationOutcome('mismatch', 'multiple_matches', [
                'count' => (string) $candidates->count(),
                'matches' => $this->listed($candidates),
            ]);
        }

        $code = $this->queryCode($verification);

        if ($code !== null) {
            return $this->codeMissOutcome($verification, $code);
        }

        $claimed = $this->candidates($verification, claimed: true)->sortByDesc('received_at')->first();

        if ($claimed !== null) {
            return new VerificationOutcome('reused', 'already_confirmed', [
                'confirmed_at' => $claimed->received_at->toISOString(),
            ]);
        }

        return new VerificationOutcome('retry', 'pending');
    }

    public function matchWithoutAmount(Verification $verification): VerificationOutcome
    {
        $candidates = $this->pool(
            $verification,
            (int) config('payguard.query.no_amount_minutes'),
            claimed: false,
        );

        $candidates = $this->applyCodeFilter($candidates, $this->queryCode($verification));

        $wanted = $verification->extracted['query']['payer_name'] ?? null;

        if (! blank($wanted)) {
            $candidates = $candidates
                ->filter(fn (ReceivedPayment $payment) => self::payerMatches($wanted, $payment->payer_name))
                ->values();
        }

        if ($candidates->count() === 1) {
            $payment = $candidates->first();

            return new VerificationOutcome(
                status: 'verified',
                reasonCode: 'amountless_single',
                receivedPaymentId: $payment->id,
                params: ['amount' => (string) $payment->amount],
            );
        }

        if ($candidates->count() > 1) {
            return new VerificationOutcome('not_found', 'amountless_multiple', [
                'count' => (string) $candidates->count(),
                'matches' => $this->listed($candidates, withAmount: true),
            ]);
        }

        if ($this->queryCode($verification) !== null) {
            return new VerificationOutcome('not_found', 'not_found');
        }

        return new VerificationOutcome('not_found', 'ask_amount');
    }

    public static function payerMatches(?string $query, ?string $payer): bool
    {
        $wanted = self::normalize($query);
        $actual = self::normalize($payer);

        if ($wanted === '' || $actual === '') {
            return false;
        }

        if (str_starts_with($actual, $wanted)) {
            return true;
        }

        foreach (explode(' ', $actual) as $token) {
            if (str_starts_with($token, $wanted)) {
                return true;
            }
        }

        return false;
    }

    private function codeMissOutcome(Verification $verification, string $code): VerificationOutcome
    {
        $claimed = $this->candidates($verification, claimed: true)->sortByDesc('received_at')->first();

        if ($claimed !== null) {
            return new VerificationOutcome('reused', 'already_confirmed', [
                'confirmed_at' => $claimed->received_at->toISOString(),
            ]);
        }

        $pool = $this->pool($verification, (int) config('payguard.query.window_minutes'), claimed: false);

        $sameAmount = $pool->filter(
            fn (ReceivedPayment $payment) => bccomp((string) $payment->amount, (string) $verification->amount, 2) === 0
        );

        if ($sameAmount->isNotEmpty()) {
            return new VerificationOutcome('mismatch', 'mismatch_code');
        }

        $withCode = $pool->first(
            fn (ReceivedPayment $payment) => $payment->security_code !== null && $payment->security_code !== ''
                && $payment->security_code === $code
        );

        if ($withCode !== null) {
            return new VerificationOutcome('mismatch', 'mismatch_amount', [
                'amount' => (string) $withCode->amount,
                'expected' => (string) $verification->amount,
            ]);
        }

        return new VerificationOutcome('not_found', 'not_found');
    }

    private function candidates(Verification $verification, bool $claimed): Collection
    {
        $pool = $this->pool($verification, (int) config('payguard.query.window_minutes'), $claimed);

        $sameAmount = $pool->filter(
            fn (ReceivedPayment $payment) => bccomp((string) $payment->amount, (string) $verification->amount, 2) === 0
        );

        $wanted = $verification->extracted['query']['payer_name'] ?? null;

        if (! blank($wanted)) {
            $sameAmount = $sameAmount
                ->filter(fn (ReceivedPayment $payment) => self::payerMatches($wanted, $payment->payer_name))
                ->values();
        }

        return $this->applyCodeFilter($sameAmount, $this->queryCode($verification));
    }

    private function pool(Verification $verification, int $windowMinutes, bool $claimed): Collection
    {
        return ReceivedPayment::forBusiness($verification->business_id)
            ->where('wallet', $verification->wallet)
            ->whereNotNull('amount')
            ->when(
                $verification->trial_key !== null,
                fn ($query) => $query->where('trial_key', $verification->trial_key),
                fn ($query) => $query->whereNull('trial_key'),
            )
            ->where('received_at', '>=', now()->subMinutes($windowMinutes))
            ->when($claimed,
                fn ($query) => $query->whereHas('verification'),
                fn ($query) => $query->whereDoesntHave('verification'),
            )
            ->orderBy('received_at')
            ->get();
    }

    private function applyCodeFilter(Collection $payments, ?string $code): Collection
    {
        if ($code === null) {
            return $payments->values();
        }

        return $payments
            ->filter(fn (ReceivedPayment $payment) => $payment->security_code === $code)
            ->values();
    }

    private function queryCode(Verification $verification): ?string
    {
        $code = $verification->security_code;

        return $code !== null && $code !== '' ? $code : null;
    }

    private function listed(Collection $candidates, bool $withAmount = false): array
    {
        return $candidates->sortByDesc('received_at')
            ->take((int) config('payguard.query.max_listed_matches'))
            ->values()
            ->map(fn (ReceivedPayment $payment) => array_filter([
                'amount' => $withAmount ? (string) $payment->amount : null,
                'payer' => $payment->payer_name,
                'received_at' => $payment->received_at->toISOString(),
            ], fn ($value) => $value !== null))
            ->all();
    }

    private static function normalize(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        return trim((string) preg_replace(
            '/\s+/',
            ' ',
            str_replace('*', '', Str::ascii(mb_strtolower($name)))
        ));
    }
}
