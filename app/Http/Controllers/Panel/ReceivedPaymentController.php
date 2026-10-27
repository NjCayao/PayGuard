<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ReceivedPaymentIndexRequest;
use App\Http\Resources\Panel\ReceivedPaymentResource;
use App\Models\ReceivedPayment;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReceivedPaymentController extends Controller
{
    public function index(ReceivedPaymentIndexRequest $request): AnonymousResourceCollection
    {
        $query = ReceivedPayment::forBusiness($request->user()->business_id)
            ->with('device')
            ->orderByDesc('received_at');

        if ($request->filled('date')) {
            $timezone = $request->user()->business->timezone;
            $start = Carbon::parse($request->string('date'), $timezone)->startOfDay()->utc();
            $end = $start->copy()->addDay();

            $query->whereBetween('received_at', [$start, $end]);
        }

        return ReceivedPaymentResource::collection(
            $query->paginate(config('payguard.received_payments_per_page'))
        );
    }
}
