<?php

namespace App\Services\Payments;

interface NotificationParser
{
    public function wallet(): string;

    /**
     * Read a notification. Returns null when it is not a received payment
     * (outgoing transfers, promos, purchases) so it is never stored.
     */
    public function parse(string $text): ?ParsedNotification;
}
