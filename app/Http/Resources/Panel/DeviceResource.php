<?php

namespace App\Http\Resources\Panel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_online' => $this->isOnline(),
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'last_payment_at' => $this->last_payment_at?->toISOString(),
        ];
    }
}
