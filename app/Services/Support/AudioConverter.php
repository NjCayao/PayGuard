<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class AudioConverter
{
    public function toWav(string $inputPath, string $outputPath): void
    {
        $result = Process::timeout((int) config('payguard.support.ffmpeg_timeout'))->run([
            (string) config('payguard.support.ffmpeg_path'),
            '-y',
            '-i', $inputPath,
            '-ac', '1',
            '-ar', '24000',
            '-c:a', 'pcm_s16le',
            '-map_metadata', '-1',
            $outputPath,
        ]);

        if ($result->failed() || ! is_file($outputPath) || filesize($outputPath) === 0) {
            @unlink($outputPath);

            throw new RuntimeException('Audio conversion failed.');
        }
    }
}
