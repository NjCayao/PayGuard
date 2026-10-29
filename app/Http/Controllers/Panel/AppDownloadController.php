<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AppDownloadController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => [
            'url' => config('payguard.apk.url'),
            'version' => config('payguard.apk.version'),
            'date' => config('payguard.apk.date'),
        ]]);
    }
}
