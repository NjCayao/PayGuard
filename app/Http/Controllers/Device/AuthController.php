<?php

namespace App\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\Device\DeviceLoginRequest;
use App\Services\Device\DeviceAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(DeviceLoginRequest $request, DeviceAuthService $service): JsonResponse
    {
        $result = $service->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('brand')->toString(),
            $request->string('model')->toString(),
        );

        if ($result === null) {
            return response()->json([
                'message' => 'El correo o la contraseña no son correctos.',
            ], 401);
        }

        return response()->json(['data' => [
            'token' => $result['token'],
            'device' => [
                'id' => $result['device']->id,
                'name' => $result['device']->name,
            ],
        ]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => true]);
    }
}
