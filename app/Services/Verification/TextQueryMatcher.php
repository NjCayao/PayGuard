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
            $listed = $candidates->sortByDesc('received_at')
                ->take((int) config('payguard.query.max_listed_matches'))
                ->values();

            return new VerificationOutcome('mismatch', 'multiple_matches', [
                'count' => (string) $candidates->count(),
                'matches' => $listed->map(fn (ReceivedPayment $payment) => [
                    'payer' => $payment->payer_name,
                    'received_at' => $payment->received_at->toISOString(),
                ])->all(),
            ]);
        }

        $claimed = $this->candidates($verification, claimed: true)->sortByDesc('received_at')->first();

        if ($claimed !== null) {
            return new VerificationOutcome('reused', 'already_confirmed', [
                'confirmed_at' => $claimed->received_at->toISOString(),
            ]);
        }

        return new VerificationOutcome('retry', 'pending');
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

    private function candidates(Verification $verification, bool $claimed): Collection
    {
        $pool = ReceivedPayment::forBusiness($verification->business_id)
            ->where('wallet', $verification->wallet)
            ->whereNotNull('amount')
            ->where('received_at', '>=', now()->subMinutes((int) config('payguard.query.window_minutes')))
            ->when($claimed,
                fn ($query) => $query->whereHas('verification'),
                fn ($query) => $query->whereDoesntHave('verification'),
            )
            ->orderBy('received_at')
            ->get();

        $sameAmount = $pool->filter(
            fn (ReceivedPayment $payment) => bccomp((string) $payment->amount, (string) $verification->amount, 2) === 0
        );

        $wanted = $verification->extracted['query']['payer_name'] ?? null;

        if (blank($wanted)) {
            return $sameAmount->values();
        }

        return $sameAmount
            ->filter(fn (ReceivedPayment $payment) => self::payerMatches($wanted, $payment->payer_name))
            ->values();
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
