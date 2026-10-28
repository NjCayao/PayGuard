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
            ->withoutTrial()
            ->whereBetween('received_at', [$start, $end]);

        $rows = Verification::forBusiness($business->id)
            ->listable()
            ->withoutTrial()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, reason_code, COUNT(*) as total')
            ->groupBy('status', 'reason_code')
            ->get();

        $counts = [];
        $needsCapture = 0;
        $alreadyConfirmed = 0;

        foreach ($rows as $row) {
            if ($row->reason_code === 'multiple_matches') {
                $needsCapture += $row->total;

                continue;
            }

            if ($row->reason_code === 'already_confirmed') {
                $alreadyConfirmed += $row->total;

                continue;
            }

            $counts[$row->status] = ($counts[$row->status] ?? 0) + $row->total;
        }

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
                'needs_capture' => $needsCapture,
                'already_confirmed' => $alreadyConfirmed,
            ],
            'owner_alerts_sent' => $alerts,
            'device' => [
                'online' => $this->presence->online($business),
                'last_seen_at' => $this->presence->lastSeenAt($business)?->toISOString(),
            ],
        ];
    }
}
