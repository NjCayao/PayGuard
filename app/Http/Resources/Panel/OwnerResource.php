<?php

namespace App\Http\Resources\Panel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OwnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'business' => [
                'id' => $this->business->id,
                'name' => $this->business->name,
                'owner_whatsapp' => $this->business->owner_whatsapp,
                'yape_holder_name' => $this->business->yape_holder_name,
                'match_window_minutes' => $this->business->match_window_minutes,
                'recheck_minutes' => $this->business->recheck_minutes,
                'timezone' => $this->business->timezone,
            ],
        ];
    }
}
