<?php

namespace App\Services\Meta;

use App\Models\Business;
use App\Models\Verification;
use App\Services\Support\Amounts;
use Carbon\Carbon;
use Throwable;

readonly class ScreenshotData
{
    public function __construct(
        public string $wallet,
        public ?string $amount,
        public ?string $recipientName,
        public Carbon $paidAt,
        public ?string $securityCode,
        public ?string $operationCode,
        public array $editSignals,
        public array $raw,
        public int $aiMs,
    ) {}

    public static function fromModel(array $data, Business $business, array $raw, int $aiMs): self
    {
        $amount = $data['amount'] ?? null;
        $operationCode = $data['operation_code'] ?? null;

        return new self(
            wallet: in_array($data['wallet'] ?? null, ['yape', 'plin'], true)
                ? $data['wallet']
                : 'yape',
            amount: Amounts::normalize($amount === null ? null : (string) $amount),
            recipientName: self::cleanText($data['recipient_name'] ?? null, 120),
            paidAt: self::paidAt($data, $business),
            securityCode: self::securityCode($data['security_code'] ?? null),
            operationCode: self::cleanText(is_int($operationCode) ? (string) $operationCode : $operationCode, 40),
            editSignals: self::editSignals($data['edit_signals'] ?? null),
            raw: $raw,
            aiMs: $aiMs,
        );
    }

    public function applyTo(Verification $verification): void
    {
        $verification->update([
            'wallet' => $this->wallet,
            'amount' => $this->amount,
            'recipient_name' => $this->recipientName,
            'paid_at' => $this->paidAt,
            'security_code' => $this->securityCode,
            'operation_code' => $this->operationCode,
            'edit_signals' => $this->editSignals === [] ? null : $this->editSignals,
            'extracted' => $this->raw,
            'ai_ms' => $this->aiMs,
        ]);
    }

    private static function paidAt(array $data, Business $business): Carbon
    {
        $now = now($business->timezone);

        $date = is_string($data['paid_date'] ?? null)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['paid_date']) === 1
            ? $data['paid_date']
            : $now->toDateString();

        $time = is_string($data['paid_time'] ?? null)
            && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['paid_time']) === 1
            ? $data['paid_time']
            : $now->format('H:i');

        try {
            return Carbon::createFromFormat('Y-m-d H:i', "{$date} ".substr($time, 0, 5), $business->timezone)
                ->setTimezone('UTC');
        } catch (Throwable) {
            return $now->setTimezone('UTC');
        }
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

    private static function editSignals(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $signals = [];

        foreach (array_slice($value, 0, 10) as $signal) {
            $clean = self::cleanText($signal, 200);

            if ($clean !== null) {
                $signals[] = $clean;
            }
        }

        return $signals;
    }
}
