<?php

namespace Tests\Feature\Meta;

use App\Services\Meta\QueryAmountFallback;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QueryAmountFallbackTest extends TestCase
{
    #[DataProvider('amountTexts')]
    public function test_extracts_amount(string $text, string $expected): void
    {
        $data = app(QueryAmountFallback::class)->parse($text);

        $this->assertTrue($data->isQuery);
        $this->assertSame($expected, $data->amount);
    }

    public static function amountTexts(): array
    {
        return [
            'digits with soles' => ['yape de 50 soles', '50.00'],
            'plain digits' => ['yape de 50', '50.00'],
            'soles symbol' => ['¿llegó el S/ 20.50?', '20.50'],
            'slang lucas' => ['50 lucas', '50.00'],
            'number word' => ['me yapearon cincuenta soles', '50.00'],
            'words with cents' => ['llegó un yape de veinte con cincuenta', '20.50'],
            'thousands' => ['yape de dos mil', '2000.00'],
        ];
    }

    public function test_extracts_payer_name(): void
    {
        $data = app(QueryAmountFallback::class)->parse('¿llegó un yape de 20 de Juan?');

        $this->assertSame('20.00', $data->amount);
        $this->assertSame('Juan', $data->payerName);
    }

    public function test_extracts_security_code(): void
    {
        $data = app(QueryAmountFallback::class)->parse('yape de 50, código 944');

        $this->assertSame('50.00', $data->amount);
        $this->assertSame('944', $data->securityCode);
    }

    public function test_extracts_short_code(): void
    {
        $data = app(QueryAmountFallback::class)->parse('cód 123 yape de 10');

        $this->assertSame('10.00', $data->amount);
        $this->assertSame('123', $data->securityCode);
    }

    public function test_detects_plin(): void
    {
        $data = app(QueryAmountFallback::class)->parse('plin de 30');

        $this->assertSame('plin', $data->wallet);
        $this->assertSame('30.00', $data->amount);
    }

    public function test_without_amount_returns_null_amount(): void
    {
        $data = app(QueryAmountFallback::class)->parse('me yapearon');

        $this->assertTrue($data->isQuery);
        $this->assertNull($data->amount);
        $this->assertNull($data->payerName);
    }
}
