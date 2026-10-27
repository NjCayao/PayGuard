<?php

namespace App\Services\Payments;

class NotificationParserResolver
{
    public function forPackage(string $package): ?NotificationParser
    {
        $wallet = config('payguard.notifications.packages')[$package] ?? null;

        return match ($wallet) {
            'yape' => new YapeNotificationParser(),
            default => null,
        };
    }
}
