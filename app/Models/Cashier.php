<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cashier extends Model
{
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'whatsapp',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }
}
