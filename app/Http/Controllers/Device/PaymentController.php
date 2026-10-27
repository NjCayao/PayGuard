<?php

namespace App\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\Device\StorePaymentRequest;
use App\Services\Payments\ReceivedPaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
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
