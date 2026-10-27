<?php

namespace App\Services\Panel;

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BusinessRegistrationService
{
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $business = Business::create([
                'name' => $data['business_name'],
                'owner_whatsapp' => $data['owner_whatsapp'],
                'match_window_minutes' => config('payguard.defaults.match_window_minutes'),
                'recheck_minutes' => config('payguard.defaults.recheck_minutes'),
                'timezone' => config('payguard.defaults.timezone'),
            ]);

            return $business->users()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
        });
    }
}
