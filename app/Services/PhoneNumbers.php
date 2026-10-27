<?php

namespace App\Services;

class PhoneNumbers
{
    public static function normalize(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }
}
