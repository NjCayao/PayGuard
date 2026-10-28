<?php

namespace App\Services\Support;

class Amounts
{
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $clean = str_replace(' ', '', $raw);

        if (str_contains($clean, '.') && str_contains($clean, ',')) {
            if (strrpos($clean, '.') > strrpos($clean, ',')) {
                $clean = str_replace(',', '', $clean);
            } else {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            }
        } elseif (str_contains($clean, ',')) {
            if (preg_match('/,\d{2}$/', $clean) === 1) {
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        }

        if (! is_numeric($clean)) {
            return null;
        }

        $value = round((float) $clean, 2);

        if ($value <= 0 || $value >= 100000000) {
            return null;
        }

        return number_format($value, 2, '.', '');
    }
}
