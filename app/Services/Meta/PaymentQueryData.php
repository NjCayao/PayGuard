<?php

namespace App\Services\Meta;

use App\Models\Verification;
use App\Services\Support\Amounts;

readonly class PaymentQueryData
{
    public function __construct(
        public bool $isQuery,
        public string $wallet,
        public ?string $amount,
        public ?string $payerName,
        public ?string $securityCode,
        public array $raw,
        public int $aiMs,
    ) {}

    public static function notAQuery(): self
    {
        return new self(
            isQuery: false,
            wallet: 'yape',
            amount: null,
            payerName: null,
            securityCode: null,
            raw: ['is_payment_query' => false],
            aiMs: 0,
        );
    }

    public static function fromModel(array $data, array $raw, int $aiMs): self
    {
        $amount = $data['amount'] ?? null;

        return new self(
            isQuery: (bool) ($data['is_payment_query'] ?? false),
            wallet: in_array($data['wallet'] ?? null, ['yape', 'plin'], true)
                ? $data['wallet']
                : 'yape',
            amount: Amounts::normalize($amount === null ? null : (string) $amount),
            payerName: self::cleanText($data['payer_name'] ?? null, 120),
            securityCode: self::securityCode($data['security_code'] ?? null),
            raw: $raw,
            aiMs: $aiMs,
        );
    }

    public static function fromRules(
        string $wallet,
        ?string $amount,
        ?string $payerName,
        ?string $securityCode,
    ): self {
        return new self(
            isQuery: true,
            wallet: $wallet,
            amount: $amount,
            payerName: $payerName,
            securityCode: $securityCode,
            raw: ['fallback' => true],
            aiMs: 0,
        );
    }

    public function applyTo(Verification $verification): void
    {
        $extracted = $verification->extracted ?? [];

        $extracted['query'] = [
            'is_payment_query' => $this->isQuery,
            'wallet' => $this->wallet,
            'amount' => $this->amount,
            'payer_name' => $this->payerName,
            'security_code' => $this->securityCode,
        ];

        $verification->update([
            'wallet' => $this->wallet,
            'amount' => $this->amount,
            'security_code' => $this->securityCode,
            'extracted' => $extracted,
            'ai_ms' => ($verification->ai_ms ?? 0) + $this->aiMs,
        ]);
    }

    private static function securityCode(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $code = trim((string) $value);

        return preg_match('/^\d{3}$/', $code) === 1 ? $code : null;
    }

    private static function cleanText(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return $clean === '' ? null : mb_substr($clean, 0, $maxLength);
    }
}
