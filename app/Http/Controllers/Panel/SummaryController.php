<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Panel\DaySummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    public function index(Request $request, DaySummaryService $summary): JsonResponse
    {
        return response()->json(['data' => $summary->for($request->user()->business)]);
    }
}
