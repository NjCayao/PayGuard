<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Verification extends Model
{
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'cashier_id',
        'wa_message_id',
        'source',
        'trial_key',
        'input_type',
        'media_path',
        'media_sha256',
        'transcript',
        'extracted',
        'wallet',
        'amount',
        'recipient_name',
        'security_code',
        'paid_at',
        'operation_code',
        'edit_signals',
        'status',
        'reason',
        'reason_code',
        'received_payment_id',
        'recheck_until',
        'ai_ms',
    ];

    protected $casts = [
        'extracted' => 'array',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'edit_signals' => 'array',
        'recheck_until' => 'datetime',
        'ai_ms' => 'integer',
    ];

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(Cashier::class);
    }

    public function receivedPayment(): BelongsTo
    {
        return $this->belongsTo(ReceivedPayment::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(OwnerAlert::class);
    }

    public function scopeListable($query)
    {
        return $query->where(function ($group) {
            $group->whereNull('reason_code')->orWhere('reason_code', '!=', 'text_help');
        });
    }

    public function scopeWithoutTrial($query)
    {
        return $query->where('source', 'whatsapp');
    }

    public function isTrial(): bool
    {
        return $this->source === 'trial';
    }
}
