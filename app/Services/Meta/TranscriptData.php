<?php

namespace App\Services\Meta;

readonly class TranscriptData
{
    public function __construct(
        public ?string $text,
        public ?int $audioDurationMs,
        public int $aiMs,
        public array $raw,
    ) {}
}
