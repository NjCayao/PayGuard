<?php

namespace App\Services\Device;

use App\Models\Business;
use Carbon\Carbon;

class DevicePresence
{
    public function online(Business $business): bool
    {
        return $business->devices()
            ->where('last_seen_at', '>', now()->subMinutes(
                (int) config('payguard.device.offline_minutes')
            ))
            ->exists();
    }

    public function lastSeenAt(Business $business): ?Carbon
    {
        $lastSeen = $business->devices()->max('last_seen_at');

        return $lastSeen === null ? null : Carbon::parse($lastSeen);
    }
}
