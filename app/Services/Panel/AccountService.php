<?php

namespace App\Services\Panel;

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountService
{
    public function updateEmail(User $user, string $email): void
    {
        $user->update(['email' => $email]);
    }

    public function updatePassword(User $user, string $newPassword): void
    {
        $user->update(['password' => $newPassword]);

        // Closes the other panel sessions but keeps the current one. Device
        // tokens live in another table and stay valid.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getAuthIdentifier())
                ->where('id', '!=', session()->getId())
                ->delete();
        }
    }

    public function updateOwnerWhatsapp(Business $business, string $ownerWhatsapp): void
    {
        $business->update(['owner_whatsapp' => $ownerWhatsapp]);
    }
}
