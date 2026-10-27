<?php

namespace App\Services\Payments;

readonly class ParsedNotification
{
    public function __construct(
        public string $wallet,
        public ?string $amount,
        public ?string $payerName,
        public ?string $securityCode,
        public ?string $operationCode,
    ) {}

    public function isComplete(): bool
    {
        return $this->amount !== null && $this->payerName !== null;
    }
}
