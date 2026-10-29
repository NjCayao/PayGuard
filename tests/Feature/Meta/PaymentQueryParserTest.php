<?php

namespace Tests\Feature\Meta;

use App\Services\Meta\PaymentQueryParser;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentQueryParserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payguard.meta.base_url', 'https://api.meta.ai/v1');
        config()->set('payguard.meta.api_key', 'test-key');
    }

    public function test_parses_payment_query_with_structured_output(): void
    {
        $this->fakeModel([
            'is_payment_query' => true,
            'wallet' => 'yape',
            'amount' => '20.00',
            'payer_name' => 'Juan',
        ]);

        $data = app(PaymentQueryParser::class)->parse('¿llegó un yape de 20 de Juan?');

        $this->assertTrue($data->isQuery);
        $this->assertSame('yape', $data->wallet);
        $this->assertSame('20.00', $data->amount);
        $this->assertSame('Juan', $data->payerName);
        $this->assertIsInt($data->aiMs);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.meta.ai/v1/chat/completions'
                && $request['response_format']['json_schema']['name'] === 'payment_query'
                && $request['max_tokens'] === (int) config('payguard.meta.query_max_tokens');
        });
    }

    public function test_parses_security_code(): void
    {
        $this->fakeModel([
            'is_payment_query' => true,
            'wallet' => 'yape',
            'amount' => '50.00',
            'payer_name' => null,
            'security_code' => '944',
        ]);

        $data = app(PaymentQueryParser::class)->parse('yape de 50, código 944');

        $this->assertTrue($data->isQuery);
        $this->assertSame('50.00', $data->amount);
        $this->assertSame('944', $data->securityCode);
    }

    public function test_unknown_wallet_defaults_to_yape(): void
    {
        $this->fakeModel([
            'is_payment_query' => true,
            'wallet' => 'unknown',
            'amount' => '50.00',
            'payer_name' => null,
        ]);

        $data = app(PaymentQueryParser::class)->parse('me yapearon cincuenta soles');

        $this->assertTrue($data->isQuery);
        $this->assertSame('yape', $data->wallet);
        $this->assertSame('50.00', $data->amount);
        $this->assertNull($data->payerName);
    }

    public function test_model_rejection_is_not_a_query(): void
    {
        $this->fakeModel([
            'is_payment_query' => false,
            'wallet' => 'yape',
            'amount' => null,
            'payer_name' => null,
        ]);

        $data = app(PaymentQueryParser::class)->parse('yape es lo máximo');

        $this->assertFalse($data->isQuery);
        $this->assertNull($data->amount);
    }

    public function test_greeting_skips_model_call(): void
    {
        Http::fake();

        $data = app(PaymentQueryParser::class)->parse('Hola, buenos días');

        $this->assertFalse($data->isQuery);
        $this->assertSame(0, $data->aiMs);
        Http::assertNothingSent();
    }

    #[DataProvider('queryTexts')]
    public function test_prefilter_detects_queries(string $text): void
    {
        $this->assertTrue(app(PaymentQueryParser::class)->looksLikeQuery($text));
    }

    public static function queryTexts(): array
    {
        return [
            'digits' => ['yape de 50'],
            'question' => ['¿llegó un yape de 20 de Juan?'],
            'number words' => ['me yapearon cincuenta soles'],
            'slang' => ['50 lucas'],
            'decimal words' => ['veinte con cincuenta'],
            'received verb' => ['recibiste mi pago'],
            'confirm verb' => ['confírmame porfa'],
        ];
    }

    #[DataProvider('greetingTexts')]
    public function test_prefilter_ignores_greetings(?string $text): void
    {
        $this->assertFalse(app(PaymentQueryParser::class)->looksLikeQuery($text));
    }

    public static function greetingTexts(): array
    {
        return [
            'hello' => ['Hola'],
            'thanks' => ['gracias'],
            'ok' => ['ok'],
            'how are you' => ['qué tal?'],
            'empty' => [''],
            'null' => [null],
        ];
    }

    private function fakeModel(array $content): void
    {
        Http::fake([
            'api.meta.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($content)]]],
            ]),
        ]);
    }
}
