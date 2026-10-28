<?php

namespace App\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\Device\DevicePaymentIndexRequest;
use App\Http\Requests\Device\StorePaymentRequest;
use App\Http\Resources\Panel\ReceivedPaymentResource;
use App\Models\ReceivedPayment;
use App\Services\Payments\ReceivedPaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function index(DevicePaymentIndexRequest $request): AnonymousResourceCollection
    {
        $device = $request->user();
        $limit = (int) ($request->validated()['limit'] ?? 20);

        $payments = ReceivedPayment::forBusiness($device->business_id)
            ->withoutTrial()
            ->with('device')
            ->orderByDesc('received_at')
            ->limit($limit)
            ->get();

        $start = Carbon::now($device->business->timezone)->startOfDay()->utc();

        $todayCount = ReceivedPayment::forBusiness($device->business_id)
            ->withoutTrial()
            ->whereBetween('received_at', [$start, $start->copy()->addDay()])
            ->count();

        return ReceivedPaymentResource::collection($payments)
            ->additional(['meta' => ['today_count' => $todayCount]]);
    }

    public function store(StorePaymentRequest $request, ReceivedPaymentService $service): JsonResponse
    {
        $result = $service->store($request->user(), $request->validated());

        if (! $result->saved) {
            return response()->json(['data' => ['saved' => false]]);
        }

        if ($result->duplicate) {
            return response()->json(['data' => [
                'duplicate' => true,
                'id' => $result->payment?->id,
            ]]);
        }

        return response()->json(['data' => [
            'id' => $result->payment->id,
            'parsed' => $result->payment->parsed,
        ]], 201);
    }
}
