<?php

namespace App\Services\Demo;

use App\Models\Business;

class DemoProtection
{
    public static function message(): string
    {
        return (string) config('payguard.demo.blocked_message');
    }

    public static function ensureWritable(Business $business): void
    {
        if ($business->isDemo()) {
            abort(403, self::message());
        }
    }
}
