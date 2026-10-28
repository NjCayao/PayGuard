<?php

namespace App\Services\Panel;

use App\Models\Business;
use App\Models\Verification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class VerificationListQuery
{
    public function for(Business $business, array $filters): Builder
    {
        $query = Verification::forBusiness($business->id)
            ->listable()
            ->orderByDesc('created_at');

        if (! empty($filters['cashier_id'])) {
            $query->where('cashier_id', $filters['cashier_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['input_type'])) {
            $query->where('input_type', $filters['input_type']);
        }

        $start = ! empty($filters['date_from'])
            ? Carbon::parse($filters['date_from'], $business->timezone)->startOfDay()->utc()
            : null;

        $end = ! empty($filters['date_to'])
            ? Carbon::parse($filters['date_to'], $business->timezone)->endOfDay()->utc()
            : null;

        if ($start !== null && $end !== null) {
            $query->whereBetween('created_at', [$start, $end]);
        } elseif ($start !== null) {
            $query->where('created_at', '>=', $start);
        } elseif ($end !== null) {
            $query->where('created_at', '<=', $end);
        }

        return $query;
    }
}
