<?php

namespace App\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->user()->update(['last_seen_at' => now()]);

        return response()->json(['data' => ['ok' => true]]);
    }
}
