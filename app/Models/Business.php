<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'owner_whatsapp',
        'yape_holder_name',
        'match_window_minutes',
        'recheck_minutes',
        'timezone',
    ];

    protected $casts = [
        'match_window_minutes' => 'integer',
        'recheck_minutes' => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function cashiers(): HasMany
    {
        return $this->hasMany(Cashier::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function receivedPayments(): HasMany
    {
        return $this->hasMany(ReceivedPayment::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }
}
