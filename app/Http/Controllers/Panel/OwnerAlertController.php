<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\OwnerAlertIndexRequest;
use App\Http\Resources\Panel\OwnerAlertResource;
use App\Models\OwnerAlert;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OwnerAlertController extends Controller
{
    public function index(OwnerAlertIndexRequest $request): AnonymousResourceCollection
    {
        $business = $request->user()->business;
        $filters = $request->validated();

        $query = OwnerAlert::forBusiness($business->id)
            ->whereDoesntHave('verification', fn ($alert) => $alert->where('source', 'trial'))
            ->with('verification.cashier')
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->where(
                'created_at',
                '>=',
                Carbon::parse($filters['date_from'], $business->timezone)->startOfDay()->utc()
            );
        }

        if (! empty($filters['date_to'])) {
            $query->where(
                'created_at',
                '<=',
                Carbon::parse($filters['date_to'], $business->timezone)->endOfDay()->utc()
            );
        }

        return OwnerAlertResource::collection(
            $query->paginate(config('payguard.owner_alerts_per_page'))
        );
    }
}
