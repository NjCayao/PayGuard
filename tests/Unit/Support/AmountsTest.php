<?php

namespace Tests\Unit\Support;

use App\Services\Support\Amounts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AmountsTest extends TestCase
{
    #[DataProvider('amounts')]
    public function test_normalizes_amounts(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, Amounts::normalize($raw));
    }

    public static function amounts(): array
    {
        return [
            'plain' => ['50', '50.00'],
            'decimals' => ['50.00', '50.00'],
            'comma decimals' => ['50,00', '50.00'],
            'thousands dot decimal comma' => ['1.234,56', '1234.56'],
            'thousands comma decimal dot' => ['1,234.56', '1234.56'],
            'thousands comma only' => ['1,234', '1234.00'],
            'spaces' => ['1 234.50', '1234.50'],
            'null' => [null, null],
            'text' => ['abc', null],
            'zero' => ['0', null],
            'negative' => ['-5', null],
            'too large' => ['100000000', null],
        ];
    }
}
