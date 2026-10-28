<?php

namespace App\Services\Payments;

use App\Services\Support\Amounts;

class YapeNotificationParser implements NotificationParser
{
    public function wallet(): string
    {
        return 'yape';
    }

    public function parse(string $text): ?ParsedNotification
    {
        $rules = config('payguard.notifications.yape');

        foreach ($rules['ignore_patterns'] as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return null;
            }
        }

        $received = false;

        foreach ($rules['received_markers'] as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $received = true;
                break;
            }
        }

        if (! $received) {
            return null;
        }

        $payer = null;
        $rest = $text;

        foreach ($rules['payment_patterns'] as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                $payer = $this->cleanName($matches['payer']);
                $rest = $matches['rest'];
                break;
            }
        }

        return new ParsedNotification(
            wallet: 'yape',
            amount: $this->findAmount($rest, $rules['amount_patterns']),
            payerName: $payer,
            securityCode: $this->findFirst($text, $rules['security_code_patterns'], 'code'),
            operationCode: $this->findFirst($text, $rules['operation_patterns'], 'code', 40),
        );
    }

    private function findAmount(string $text, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                $amount = $this->normalizeAmount($matches['amount']);

                if ($amount !== null) {
                    return $amount;
                }
            }
        }

        return null;
    }

    private function findFirst(string $text, array $patterns, string $group, int $maxLength = 120): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1 && ($matches[$group] ?? '') !== '') {
                return mb_substr(trim($matches[$group]), 0, $maxLength);
            }
        }

        return null;
    }

    private function cleanName(string $name): ?string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        return $clean === '' ? null : mb_substr($clean, 0, 120);
    }

    private function normalizeAmount(string $raw): ?string
    {
        return Amounts::normalize($raw);
    }
}
