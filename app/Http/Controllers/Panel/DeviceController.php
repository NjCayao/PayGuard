<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Resources\Panel\DeviceResource;
use App\Models\Device;
use App\Services\Demo\DemoProtection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeviceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $devices = Device::forBusiness($request->user()->business_id)
            ->orderBy('name')
            ->get();

        return DeviceResource::collection($devices);
    }

    public function unlink(Request $request, int $device): JsonResponse
    {
        DemoProtection::ensureWritable($request->user()->business);

        $model = Device::forBusiness($request->user()->business_id)->findOrFail($device);
        $model->tokens()->delete();

        return response()->json(['data' => true]);
    }
}
