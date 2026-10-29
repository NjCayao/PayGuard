<?php

namespace App\Services\Meta;

use App\Services\Support\Amounts;

class QueryAmountFallback
{
    private const NUMBERS = [
        'uno' => 1, 'una' => 1, 'un' => 1,
        'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5,
        'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9,
        'diez' => 10, 'once' => 11, 'doce' => 12, 'trece' => 13,
        'catorce' => 14, 'quince' => 15, 'dieciseis' => 16,
        'diecisiete' => 17, 'dieciocho' => 18, 'diecinueve' => 19,
        'veinte' => 20, 'veintiuno' => 21, 'veintidos' => 22,
        'veintitres' => 23, 'veinticuatro' => 24, 'veinticinco' => 25,
        'veintiseis' => 26, 'veintisiete' => 27, 'veintiocho' => 28,
        'veintinueve' => 29, 'treinta' => 30, 'cuarenta' => 40,
        'cincuenta' => 50, 'sesenta' => 60, 'setenta' => 70,
        'ochenta' => 80, 'noventa' => 90, 'cien' => 100,
        'ciento' => 100, 'mil' => 1000,
    ];

    private const NAME_BLOCKLIST = [
        'soles', 'sol', 'lucas', 'luca', 'yape', 'plin', 'codigo',
        'código', 'cod', 'seguridad', 'pago', 'pagos', 'plata',
        'cuanto', 'cuánto', 'favor', 'porfa',
    ];

    public function parse(string $text): PaymentQueryData
    {
        return PaymentQueryData::fromRules(
            wallet: $this->wallet($text),
            amount: $this->amount($text),
            payerName: $this->payerName($text),
            securityCode: $this->securityCode($text),
        );
    }

    private function wallet(string $text): string
    {
        return preg_match('/\bplin\b/iu', $text) === 1 ? 'plin' : 'yape';
    }

    private function amount(string $text): ?string
    {
        if (preg_match('/S\/\.?\s*([\d.,]+)/u', $text, $matches) === 1) {
            return Amounts::normalize($matches[1]);
        }

        if (preg_match('/([\d.,]+)\s*(?:soles?|lucas?)/iu', $text, $matches) === 1) {
            return Amounts::normalize($matches[1]);
        }

        if (preg_match('/\b(?:yape(?:aron|o|aste)?|plin)\s+de\s+([\d.,]+)/iu', $text, $matches) === 1) {
            return Amounts::normalize($matches[1]);
        }

        return $this->amountFromWords($text);
    }

    private function amountFromWords(string $text): ?string
    {
        $words = preg_split('/[^\p{L}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false) {
            return null;
        }

        $ascii = array_map(fn (string $word) => $this->ascii($word), $words);

        foreach ($ascii as $index => $word) {
            if (($word === 'mil' || $word === 'lucas' || $word === 'soles') && $index > 0) {
                $previous = self::NUMBERS[$ascii[$index - 1]] ?? null;

                if ($previous !== null && $previous < 1000) {
                    return Amounts::normalize((string) ($word === 'mil' ? $previous * 1000 : $previous));
                }
            }

            if ($word === 'con' && $index > 0 && isset($ascii[$index + 1])) {
                $whole = self::NUMBERS[$ascii[$index - 1]] ?? null;
                $cents = self::NUMBERS[$ascii[$index + 1]] ?? null;

                if ($whole !== null && $cents !== null && $cents < 100) {
                    return Amounts::normalize("{$whole}.{$cents}");
                }
            }
        }

        foreach ($ascii as $word) {
            if (isset(self::NUMBERS[$word])) {
                return Amounts::normalize((string) self::NUMBERS[$word]);
            }
        }

        return null;
    }

    private function payerName(string $text): ?string
    {
        if (preg_match_all('/\bde\s+([\p{L}]+)/iu', $text, $matches) !== false) {
            foreach ($matches[1] as $candidate) {
                if (! in_array(mb_strtolower($candidate), self::NAME_BLOCKLIST, true)) {
                    return mb_convert_case($candidate, MB_CASE_TITLE);
                }
            }
        }

        return null;
    }

    private function securityCode(string $text): ?string
    {
        if (preg_match('/(?:c[óo]digo|c[óo]d\.?)(?:\s*de\s*seguridad)?\s*[:#-]?\s*(\d{3})/iu', $text, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function ascii(string $word): string
    {
        return (string) str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n'],
            $word
        );
    }
}
