<?php

namespace App\Http\Resources\Panel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivedPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wallet' => $this->wallet,
            'amount' => $this->amount,
            'payer_name' => $this->payer_name,
            'security_code' => $this->security_code,
            'operation_code' => $this->operation_code,
            'received_at' => $this->received_at?->toISOString(),
            'parsed' => $this->parsed,
            'device' => [
                'id' => $this->device->id,
                'name' => $this->device->name,
            ],
        ];
    }
}
