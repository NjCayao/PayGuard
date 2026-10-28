<?php

namespace App\Services\Demo;

use App\Models\Business;
use App\Models\Verification;
use App\Services\Verification\CashierReplies;

readonly class TrialResult
{
    public function __construct(
        public string $status,
        public ?string $reasonCode,
        public array $ai,
        public ?array $match,
        public string $reply,
        public bool $alertSimulated,
    ) {}

    public static function fromVerification(Verification $verification, CashierReplies $replies, Business $business): self
    {
        $verification->loadMissing('receivedPayment');

        return new self(
            status: $verification->status,
            reasonCode: $verification->reason_code,
            ai: self::ai($verification, $business),
            match: self::match($verification, $business),
            reply: $replies->replyFor($verification),
            alertSimulated: $verification->alerts()->where('status', 'simulated')->exists(),
        );
    }

    public static function help(Verification $verification, string $help): self
    {
        return new self(
            status: $verification->status,
            reasonCode: $verification->reason_code,
            ai: [
                'wallet' => $verification->wallet,
                'amount' => $verification->amount,
                'payer_name' => $verification->extracted['query']['payer_name'] ?? null,
                'raw_text' => $verification->extracted['raw_text'] ?? null,
                'ai_ms' => $verification->ai_ms,
            ],
            match: null,
            reply: $help,
            alertSimulated: false,
        );
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'reason_code' => $this->reasonCode,
            'ai' => $this->ai,
            'match' => $this->match,
            'reply' => $this->reply,
            'alert_simulated' => $this->alertSimulated,
        ];
    }

    private static function ai(Verification $verification, Business $business): array
    {
        if ($verification->input_type === 'image') {
            return [
                'wallet' => $verification->wallet,
                'amount' => $verification->amount,
                'recipient_name' => $verification->recipient_name,
                'paid_at' => $verification->paid_at?->copy()->setTimezone($business->timezone)->toISOString(),
                'security_code' => $verification->security_code,
                'operation_code' => $verification->operation_code,
                'edit_signals' => $verification->edit_signals ?? [],
                'ai_ms' => $verification->ai_ms,
            ];
        }

        return [
            'wallet' => $verification->wallet,
            'amount' => $verification->amount,
            'payer_name' => $verification->extracted['query']['payer_name'] ?? null,
            'raw_text' => $verification->extracted['raw_text'] ?? $verification->transcript,
            'ai_ms' => $verification->ai_ms,
        ];
    }

    private static function match(Verification $verification, Business $business): array
    {
        $payment = $verification->receivedPayment;

        $window = $verification->input_type === 'image'
            ? (int) $business->match_window_minutes
            : (int) config('payguard.query.window_minutes');

        return [
            'searched' => [
                'wallet' => $verification->wallet,
                'amount' => $verification->amount,
                'window_minutes' => $window,
            ],
            'found' => $payment === null ? null : [
                'amount' => $payment->amount,
                'payer_name' => $payment->payer_name,
                'security_code' => $payment->security_code,
                'received_at' => $payment->received_at->copy()->setTimezone($business->timezone)->toISOString(),
            ],
        ];
    }
}
