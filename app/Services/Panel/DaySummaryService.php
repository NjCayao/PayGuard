<?php

namespace App\Services\Panel;

use App\Models\Business;
use App\Models\OwnerAlert;
use App\Models\ReceivedPayment;
use App\Models\Verification;
use App\Services\Device\DevicePresence;
use Carbon\Carbon;

class DaySummaryService
{
    public function __construct(private DevicePresence $presence) {}

    public function for(Business $business): array
    {
        $start = Carbon::today($business->timezone)->utc();
        $end = $start->copy()->addDay();

        $payments = ReceivedPayment::forBusiness($business->id)
            ->whereBetween('received_at', [$start, $end]);

        $counts = Verification::forBusiness($business->id)
            ->listable()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $alerts = OwnerAlert::forBusiness($business->id)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->count();

        return [
            'date' => Carbon::today($business->timezone)->toDateString(),
            'timezone' => $business->timezone,
            'received_payments' => [
                'count' => (clone $payments)->count(),
                'total' => number_format((float) (clone $payments)->sum('amount'), 2, '.', ''),
            ],
            'verifications' => [
                'verified' => (int) ($counts['verified'] ?? 0),
                'pending' => (int) ($counts['processing'] ?? 0) + (int) ($counts['pending'] ?? 0),
                'failed' => (int) ($counts['not_found'] ?? 0)
                    + (int) ($counts['mismatch'] ?? 0)
                    + (int) ($counts['error'] ?? 0),
                'reused' => (int) ($counts['reused'] ?? 0),
            ],
            'owner_alerts_sent' => $alerts,
            'device' => [
                'online' => $this->presence->online($business),
                'last_seen_at' => $this->presence->lastSeenAt($business)?->toISOString(),
            ],
        ];
    }
}
