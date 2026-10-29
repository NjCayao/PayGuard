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
        'offline' => 'offline',
        'expired' => 'error',
        'multiple_matches' => 'multiple',
        'already_confirmed' => 'already_confirmed',
        'amountless_single' => 'verified',
        'amountless_multiple' => 'multiple',
        'ask_amount' => 'ask_amount',
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

            if ($outcome->reasonCode === 'verified') {
                $reason .= $this->codeSuffix($verification, $payment);
            }

            if ($outcome->holderSkipped) {
                $reason .= (string) config('payguard.verification.reasons.holder_skipped_suffix');
            }

            if ($this->hasEditSignals($verification) && in_array($outcome->reasonCode, self::FINAL_CODES, true)) {
                $reason .= (string) config('payguard.verification.reasons.edit_suffix');
            }

            if (! str_ends_with($reason, '.')) {
                $reason .= '.';
            }

            $reason = mb_substr($reason, 0, 255);
        }

        return [
            'reason' => $reason,
            'reply' => $this->withEmoji($outcome->reasonCode, $outcome->status, $reason),
        ];
    }

    public function replyFor(Verification $verification): string
    {
        return $this->withEmoji(
            $verification->reason_code,
            $verification->status,
            (string) ($verification->reason ?? '')
        );
    }

    public function renderExpired(string $inputType = 'image'): array
    {
        $key = $inputType === 'image' ? 'expired' : 'expired_query';
        $reason = mb_substr((string) config("payguard.verification.reasons.{$key}"), 0, 255);
        $emoji = (string) config('payguard.verification.status_emoji.error', '');

        return ['reason' => $reason, 'reply' => $emoji === '' ? $reason : "{$emoji} {$reason}"];
    }

    public static function formatAmount(?string $amount): string
    {
        return 'S/ '.number_format((float) $amount, 2, '.', ',');
    }

    private function withEmoji(?string $reasonCode, string $status, string $reason): string
    {
        $display = self::DISPLAY[$reasonCode ?? ''] ?? $status;
        $emoji = (string) config("payguard.verification.status_emoji.{$display}", '');

        return $emoji === '' ? $reason : "{$emoji} {$reason}";
    }

    private function fill(string $template, Verification $verification, VerificationOutcome $outcome, ?ReceivedPayment $payment): string
    {
        $timezone = $verification->business?->timezone ?? 'America/Lima';

        $time = $verification->paid_at ?? $payment?->received_at ?? $verification->created_at;

        return str_replace(
            [':amount', ':expected', ':payer', ':wallet', ':count', ':items', ':confirmed', ':time', ':date', ':code'],
            [
                self::formatAmount($outcome->params['amount'] ?? $verification->amount),
                self::formatAmount($outcome->params['expected'] ?? null),
                $payment?->payer_name ?? 'un cliente',
                (string) config("payguard.verification.wallet_labels.{$verification->wallet}", 'Yape'),
                (string) ($outcome->params['count'] ?? ''),
                $this->matchItems($outcome->params['matches'] ?? [], $timezone),
                $this->confirmedTime($outcome->params['confirmed_at'] ?? null, $timezone),
                $this->time($time, $timezone),
                $this->date($verification->paid_at ?? $verification->created_at, $timezone),
                $payment?->security_code ?? $verification->security_code ?? '',
            ],
            $template
        );
    }

    private function matchItems(mixed $matches, string $timezone): string
    {
        if (! is_array($matches)) {
            return '';
        }

        $items = [];

        foreach ($matches as $match) {
            if (! is_array($match) || ! isset($match['received_at'])) {
                continue;
            }

            $payer = is_string($match['payer'] ?? null) && trim($match['payer']) !== ''
                ? trim($match['payer'])
                : 'un cliente';

            $label = "{$payer} ({$this->time(Carbon::parse($match['received_at']), $timezone)})";

            if (isset($match['amount']) && is_string($match['amount'])) {
                $label = self::formatAmount($match['amount'])." de {$label}";
            }

            $items[] = $label;
        }

        if (count($items) < 2) {
            return implode('', $items);
        }

        $last = array_pop($items);

        return implode(', ', $items)." y {$last}";
    }

    private function confirmedTime(mixed $value, string $timezone): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        return $this->time(Carbon::parse($value), $timezone);
    }

    private function codeSuffix(Verification $verification, ?ReceivedPayment $payment): string
    {
        $code = $payment?->security_code ?? $verification->security_code;

        if ($code === null || $code === '') {
            return '';
        }

        return str_replace(
            ':code',
            $code,
            (string) config('payguard.verification.reasons.verified_code_suffix')
        );
    }

    private function hasEditSignals(Verification $verification): bool
    {
        return $verification->edit_signals !== null && $verification->edit_signals !== [];
    }

    private function time(Carbon $time, string $timezone): string
    {
        $local = $time->copy()->setTimezone($timezone);

        return $local->format('g:i').' '.($local->format('A') === 'AM' ? 'a. m.' : 'p. m.');
    }

    private function date(Carbon $date, string $timezone): string
    {
        return $date->copy()->setTimezone($timezone)->locale('es')->isoFormat('D MMM');
    }
}
