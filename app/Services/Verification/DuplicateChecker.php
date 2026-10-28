<?php

namespace App\Services\Verification;

use App\Models\Verification;

class DuplicateChecker
{
    public function findPrevious(Verification $verification): ?Verification
    {
        $hasOperation = ! blank($verification->operation_code);
        $hasSha = ! blank($verification->media_sha256);
        $hasCodeMinute = ! blank($verification->security_code)
            && $verification->amount !== null
            && $verification->paid_at !== null;

        if (! $hasOperation && ! $hasSha && ! $hasCodeMinute) {
            return null;
        }

        return Verification::forBusiness($verification->business_id)
            ->where('id', '!=', $verification->id)
            ->where('status', '!=', 'error')
            ->when(
                $verification->trial_key !== null,
                fn ($query) => $query->where('trial_key', $verification->trial_key),
                fn ($query) => $query->whereNull('trial_key'),
            )
            ->where(function ($query) use ($verification, $hasOperation, $hasSha, $hasCodeMinute) {
                if ($hasOperation) {
                    $query->orWhere('operation_code', $verification->operation_code);
                }

                if ($hasSha) {
                    $query->orWhere('media_sha256', $verification->media_sha256);
                }

                if ($hasCodeMinute) {
                    $minute = $verification->paid_at->copy();

                    $query->orWhere(function ($group) use ($verification, $minute) {
                        $group->where('security_code', $verification->security_code)
                            ->where('amount', $verification->amount)
                            ->whereBetween('paid_at', [
                                $minute->copy()->startOfMinute(),
                                $minute->copy()->endOfMinute(),
                            ]);
                    });
                }
            })
            ->orderBy('id')
            ->first();
    }
}
