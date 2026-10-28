<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\UpdateBusinessSettingsRequest;
use App\Http\Resources\Panel\OwnerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => new OwnerResource($request->user())]);
    }

    public function update(UpdateBusinessSettingsRequest $request): JsonResponse
    {
        $business = $request->user()->business;
        $business->update($request->validated());

        return response()->json(['data' => new OwnerResource($request->user()->fresh())]);
    }
}
