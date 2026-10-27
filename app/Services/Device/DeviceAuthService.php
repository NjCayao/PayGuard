<?php

namespace App\Services\Device;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DeviceAuthService
{
    /**
     * Authenticate the owner and provision a device for the app.
     * Returns the device and a plain-text token, or null on bad credentials.
     */
    public function login(string $email, string $password, string $brand, string $model, string $installId): ?array
    {
        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        $device = Device::firstOrCreate(
            [
                'business_id' => $user->business_id,
                'install_id' => $installId,
            ],
            [
                'name' => mb_substr(trim("{$brand} {$model}"), 0, 80),
            ]
        );

        $device->tokens()->delete();
        $token = $device->createToken('android-app')->plainTextToken;
        $device->update(['last_seen_at' => now()]);

        return ['device' => $device, 'token' => $token];
    }
}
