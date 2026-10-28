<?php

namespace App\Services\Verification;

readonly class VerificationOutcome
{
    public function __construct(
        public string $status,
        public string $reasonCode,
        public array $params = [],
        public ?int $receivedPaymentId = null,
        public bool $holderSkipped = false,
        public ?string $literalReason = null,
    ) {}
}
