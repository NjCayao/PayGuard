<?php

namespace Tests\Unit\Support;

use App\Services\Support\AudioConverter;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

class AudioConverterTest extends TestCase
{
    public function test_converts_to_mono_wav_at_native_rate(): void
    {
        Process::fake();

        $input = tempnam(sys_get_temp_dir(), 'payguard-in-');
        $output = tempnam(sys_get_temp_dir(), 'payguard-out-');
        file_put_contents($input, 'fake-ogg-bytes');
        file_put_contents($output, 'fake-wav-bytes');

        try {
            app(AudioConverter::class)->toWav($input, $output);

            Process::assertRan(function ($process) use ($input, $output) {
                $command = (array) $process->command;

                return in_array('ffmpeg', $command, true)
                    && in_array($input, $command, true)
                    && in_array($output, $command, true)
                    && in_array('pcm_s16le', $command, true)
                    && in_array('24000', $command, true)
                    && in_array('1', $command, true);
            });
        } finally {
            @unlink($input);
            @unlink($output);
        }
    }

    public function test_throws_when_ffmpeg_fails(): void
    {
        Process::fake(['*' => Process::result(exitCode: 1)]);

        $input = tempnam(sys_get_temp_dir(), 'payguard-in-');
        $output = tempnam(sys_get_temp_dir(), 'payguard-out-').'.wav';

        try {
            $this->expectException(RuntimeException::class);

            app(AudioConverter::class)->toWav($input, $output);
        } finally {
            @unlink($input);
            @unlink($output);
        }
    }

    public function test_throws_when_output_is_missing(): void
    {
        Process::fake();

        $input = tempnam(sys_get_temp_dir(), 'payguard-in-');
        $output = tempnam(sys_get_temp_dir(), 'payguard-out-');
        @unlink($output);

        try {
            $this->expectException(RuntimeException::class);

            app(AudioConverter::class)->toWav($input, $output);
        } finally {
            @unlink($input);
        }
    }
}
