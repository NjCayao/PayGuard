<?php

namespace App\Services\Verification;

use Illuminate\Support\Str;

class RecipientMatcher
{
    public static function matches(?string $screenshot, ?string $holder): bool
    {
        $shot = self::words($screenshot);
        $held = self::words($holder);

        if (count($shot) < 2 || $held === []) {
            return false;
        }

        if ($shot[0] !== $held[0]) {
            return false;
        }

        $heldIndex = 1;

        for ($i = 1; $i < count($shot); $i++) {
            $found = false;

            while ($heldIndex < count($held)) {
                if (self::wordMatches($shot[$i], $held[$heldIndex])) {
                    $found = true;
                    $heldIndex++;
                    break;
                }

                $heldIndex++;
            }

            if (! $found) {
                return false;
            }
        }

        return true;
    }

    private static function wordMatches(string $screenshot, string $holder): bool
    {
        if ($screenshot === $holder) {
            return true;
        }

        if (strlen($screenshot) === 1 && $screenshot[0] === $holder[0]) {
            return true;
        }

        return strlen($screenshot) >= 2 && str_starts_with($holder, $screenshot);
    }

    /**
     * @return list<string>
     */
    private static function words(?string $name): array
    {
        if ($name === null) {
            return [];
        }

        $clean = trim((string) preg_replace(
            '/\s+/',
            ' ',
            str_replace(['.', '*', '_'], '', Str::ascii(mb_strtolower($name)))
        ));

        if ($clean === '') {
            return [];
        }

        return array_values(array_filter(explode(' ', $clean), fn ($word) => $word !== ''));
    }
}
