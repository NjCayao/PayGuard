<?php

namespace App\Services\WhatsApp;

use App\Models\Verification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaStore
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'audio/ogg' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
    ];

    public function store(Verification $verification, string $bytes, string $mime): void
    {
        $allowed = config('payguard.whatsapp.media_allowed_mimes', []);

        if (! in_array($mime, $allowed, true) || ! isset(self::EXTENSIONS[$mime])) {
            throw new RuntimeException("Unsupported media type: {$mime}.");
        }

        $maxBytes = (int) config('payguard.whatsapp.media_max_mb') * 1024 * 1024;

        if (strlen($bytes) > $maxBytes) {
            throw new RuntimeException('Media file is too large.');
        }

        $path = "whatsapp/{$verification->business_id}/{$verification->id}.".self::EXTENSIONS[$mime];

        Storage::disk(config('payguard.whatsapp.media_disk'))->put($path, $bytes);

        $verification->update([
            'media_path' => $path,
            'media_sha256' => hash('sha256', $bytes),
        ]);
    }
}
