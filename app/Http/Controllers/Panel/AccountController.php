<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\UpdateAccountEmailRequest;
use App\Http\Requests\Panel\UpdateAccountPasswordRequest;
use App\Http\Requests\Panel\UpdateOwnerWhatsappRequest;
use App\Http\Resources\Panel\OwnerResource;
use App\Services\Demo\DemoProtection;
use App\Services\Panel\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => new OwnerResource($request->user())]);
    }

    public function updateEmail(UpdateAccountEmailRequest $request, AccountService $service): JsonResponse
    {
        DemoProtection::ensureWritable($request->user()->business);

        $service->updateEmail($request->user(), $request->validated()['email']);

        return response()->json(['data' => new OwnerResource($request->user()->fresh())]);
    }

    public function updatePassword(UpdateAccountPasswordRequest $request, AccountService $service): JsonResponse
    {
        DemoProtection::ensureWritable($request->user()->business);

        $service->updatePassword($request->user(), $request->validated()['password']);

        return response()->json(['data' => true]);
    }

    public function updateOwnerWhatsapp(UpdateOwnerWhatsappRequest $request, AccountService $service): JsonResponse
    {
        DemoProtection::ensureWritable($request->user()->business);

        $service->updateOwnerWhatsapp($request->user()->business, $request->validated()['owner_whatsapp']);

        return response()->json(['data' => new OwnerResource($request->user()->fresh())]);
    }
}
