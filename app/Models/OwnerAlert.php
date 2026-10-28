<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerAlert extends Model
{
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'verification_id',
        'to',
        'template',
        'reason',
        'wa_message_id',
        'status',
        'error',
    ];

    public function verification(): BelongsTo
    {
        return $this->belongsTo(Verification::class);
    }
}
