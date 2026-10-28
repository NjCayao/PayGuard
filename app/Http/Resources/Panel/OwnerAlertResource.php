<?php

namespace App\Http\Resources\Panel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OwnerAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toISOString(),
            'to' => $this->to,
            'template' => $this->template,
            'reason' => $this->reason,
            'status' => $this->status,
            'wa_message_id' => $this->wa_message_id,
            'error' => $this->error,
            'verification' => $this->whenLoaded('verification', fn () => [
                'id' => $this->verification->id,
                'amount' => $this->verification->amount,
                'status' => $this->verification->status,
                'cashier_name' => $this->verification->cashier?->name,
            ]),
        ];
    }
}
