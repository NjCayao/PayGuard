<?php

namespace App\Jobs;

use App\Models\OwnerAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateOwnerAlertStatus implements ShouldQueue
{
    use Queueable;

    private const RANK = [
        'queued' => 0,
        'sent' => 1,
        'delivered' => 2,
        'read' => 3,
        'failed' => 4,
    ];

    public function __construct(public array $update) {}

    public function handle(): void
    {
        $waMessageId = (string) ($this->update['wa_message_id'] ?? '');
        $status = (string) ($this->update['status'] ?? '');

        if ($waMessageId === '' || ! isset(self::RANK[$status]) || $status === 'queued') {
            return;
        }

        $alert = OwnerAlert::where('wa_message_id', $waMessageId)->first();

        if ($alert === null || self::RANK[$status] < self::RANK[$alert->status]) {
            return;
        }

        $alert->update([
            'status' => $status,
            'error' => $status === 'failed'
                ? mb_substr((string) ($this->update['error'] ?? 'No se pudo entregar.'), 0, 255)
                : $alert->error,
        ]);
    }
}
