<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Device extends Authenticatable
{
    use BelongsToBusiness, HasApiTokens, HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'install_id',
        'last_seen_at',
        'last_payment_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_payment_at' => 'datetime',
    ];

    public function receivedPayments(): HasMany
    {
        return $this->hasMany(ReceivedPayment::class);
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->greaterThan(
                now()->subMinutes(config('payguard.device.offline_minutes'))
            );
    }
}
