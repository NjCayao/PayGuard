<?php

namespace App\Http\Resources\Panel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toISOString(),
            'input_type' => $this->input_type,
            'wallet' => $this->wallet,
            'amount' => $this->amount,
            'recipient_name' => $this->recipient_name,
            'security_code' => $this->security_code,
            'operation_code' => $this->operation_code,
            'paid_at' => $this->paid_at?->toISOString(),
            'status' => $this->status,
            'reason' => $this->reason,
            'reason_code' => $this->reason_code,
            'has_image' => $this->media_path !== null,
            'image_url' => $this->media_path !== null
                ? route('panel.verifications.image', $this->id)
                : null,
            'cashier' => [
                'id' => $this->cashier->id,
                'name' => $this->cashier->name,
            ],
            'extracted' => $this->when($this->relationLoaded('alerts'), $this->extracted),
            'edit_signals' => $this->when($this->relationLoaded('alerts'), $this->edit_signals),
            'received_payment' => $this->whenLoaded('receivedPayment', fn () => [
                'id' => $this->receivedPayment->id,
                'amount' => $this->receivedPayment->amount,
                'payer_name' => $this->receivedPayment->payer_name,
                'security_code' => $this->receivedPayment->security_code,
                'received_at' => $this->receivedPayment->received_at?->toISOString(),
            ]),
            'alerts' => OwnerAlertResource::collection($this->whenLoaded('alerts')),
        ];
    }
}
