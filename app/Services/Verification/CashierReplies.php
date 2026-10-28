<?php

namespace App\Services\Verification;

use App\Models\ReceivedPayment;
use App\Models\Verification;
use Carbon\Carbon;

class CashierReplies
{
    private const DISPLAY = [
        'verified' => 'verified',
        'pending' => 'pending',
        'already_reviewing' => 'reviewing',
        'reused' => 'reused',
        'not_found' => 'not_found',
        'stale' => 'not_found',
        'unreadable' => 'not_found',
        'mismatch_recipient' => 'mismatch',
        'mismatch_code' => 'mismatch',
        'mismatch_amount' => 'mismatch',
        'expired' => 'error',
    ];

    private const FINAL_CODES = [
        'verified',
        'reused',
        'not_found',
        'stale',
        'unreadable',
        'mismatch_recipient',
        'mismatch_code',
        'mismatch_amount',
    ];

    /**
     * @return array{reason: string, reply: string}
     */
    public function render(Verification $verification, VerificationOutcome $outcome, ?ReceivedPayment $payment = null): array
    {
        if ($outcome->literalReason !== null) {
            $reason = mb_substr($outcome->literalReason, 0, 255);
        } else {
            $template = (string) config("payguard.verification.reasons.{$outcome->reasonCode}", '');

            $reason = $this->fill($template, $verification, $outcome, $payment);

            if ($outcome->holderSkipped) {
                $reason .= (string) config('payguard.verification.reasons.holder_skipped_suffix');
            }

            if ($this->hasEditSignals($verification) && in_array($outcome->reasonCode, self::FINAL_CODES, true)) {
                $reason .= (string) config('payguard.verification.reasons.edit_suffix');
            }

            $reason = mb_substr($reason, 0, 255);
        }

        $display = self::DISPLAY[$outcome->reasonCode] ?? $outcome->status;
        $emoji = (string) config("payguard.verification.status_emoji.{$display}", '');

        return [
            'reason' => $reason,
            'reply' => $emoji === '' ? $reason : "{$emoji} {$reason}",
        ];
    }

    public function renderExpired(): array
    {
        $reason = mb_substr((string) config('payguard.verification.reasons.expired'), 0, 255);

        return ['reason' => $reason, 'reply' => $reason];
    }

    public static function formatAmount(?string $amount): string
    {
        return 'S/ '.number_format((float) $amount, 2, '.', ',');
    }

    private function fill(string $template, Verification $verification, VerificationOutcome $outcome, ?ReceivedPayment $payment): string
    {
        $timezone = $verification->business?->timezone ?? 'America/Lima';

        $time = $payment?->received_at ?? $verification->paid_at ?? $verification->created_at;

        return str_replace(
            [':amount', ':expected', ':payer', ':wallet', ':time', ':date'],
            [
                self::formatAmount($outcome->params['amount'] ?? $verification->amount),
                self::formatAmount($outcome->params['expected'] ?? null),
                $payment?->payer_name ?? 'un cliente',
                (string) config("payguard.verification.wallet_labels.{$verification->wallet}", 'Yape'),
                $this->time($time, $timezone),
                $this->date($verification->paid_at ?? $verification->created_at, $timezone),
            ],
            $template
        );
    }

    private function hasEditSignals(Verification $verification): bool
    {
        return $verification->edit_signals !== null && $verification->edit_signals !== [];
    }

    private function time(Carbon $time, string $timezone): string
    {
        $local = $time->copy()->setTimezone($timezone);

        return $local->format('g:i').' '.($local->format('A') === 'AM' ? 'a. m' : 'p. m');
    }

    private function date(Carbon $date, string $timezone): string
    {
        return $date->copy()->setTimezone($timezone)->locale('es')->isoFormat('D MMM');
    }
}
