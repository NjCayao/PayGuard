<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Device extends Model
{
    use BelongsToBusiness, HasApiTokens, HasFactory;

    protected $fillable = [
        'business_id',
        'name',
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
}
