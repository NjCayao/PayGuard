<?php

namespace App\Services\Payments;

use App\Models\ReceivedPayment;

readonly class NotificationStoreResult
{
    public function __construct(
        public bool $saved,
        public bool $duplicate,
        public ?ReceivedPayment $payment,
    ) {}
}
