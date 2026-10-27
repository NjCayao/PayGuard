<?php

namespace Tests\Unit;

use App\Services\Payments\YapeNotificationParser;
use Tests\TestCase;

class YapeNotificationParserTest extends TestCase
{
    private YapeNotificationParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new YapeNotificationParser();
    }

    public function test_parses_standard_payment(): void
    {
        $parsed = $this->parser->parse('Yape! JUAN PEREZ te envió un pago por S/ 50');

        $this->assertNotNull($parsed);
        $this->assertSame('yape', $parsed->wallet);
        $this->assertSame('50.00', $parsed->amount);
        $this->assertSame('JUAN PEREZ', $parsed->payerName);
        $this->assertTrue($parsed->isComplete());
    }

    public function test_parses_amount_with_thousands_and_decimals(): void
    {
        $parsed = $this->parser->parse('Yape! MARIA te envió un pago por S/ 1,250.50');

        $this->assertNotNull($parsed);
        $this->assertSame('1250.50', $parsed->amount);
        $this->assertSame('MARIA', $parsed->payerName);
    }

    public function test_parses_security_code(): void
    {
        $parsed = $this->parser->parse("Yape! JUAN te envió un pago por S/ 20\nCódigo de seguridad: 123");

        $this->assertNotNull($parsed);
        $this->assertSame('123', $parsed->securityCode);
    }

    public function test_parses_short_security_code_variant(): void
    {
        $parsed = $this->parser->parse('Yape! JUAN te envió un pago por S/ 20. Cód. de seguridad 456');

        $this->assertNotNull($parsed);
        $this->assertSame('456', $parsed->securityCode);
    }

    public function test_parses_operation_code(): void
    {
        $parsed = $this->parser->parse('Yape! JUAN te envió un pago por S/ 20. Operación N° 12345678');

        $this->assertNotNull($parsed);
        $this->assertSame('12345678', $parsed->operationCode);
    }

    public function test_discards_outgoing_transfer(): void
    {
        $this->assertNull($this->parser->parse('Yapeaste S/ 20 a MARIA LOPEZ'));
    }

    public function test_discards_promo(): void
    {
        $this->assertNull($this->parser->parse('Yape! Aprovecha esta promoción solo por hoy'));
    }

    public function test_discards_purchase(): void
    {
        $this->assertNull($this->parser->parse('Tu compra en TOTTUS por S/ 30 con Yape'));
    }

    public function test_discards_unrelated_notification(): void
    {
        $this->assertNull($this->parser->parse('Yape! Tienes novedades en tu cuenta'));
    }

    public function test_keeps_payment_shaped_text_without_amount_as_incomplete(): void
    {
        $parsed = $this->parser->parse('Recibiste un pago por Yape, revísalo en la app');

        $this->assertNotNull($parsed);
        $this->assertNull($parsed->amount);
        $this->assertNull($parsed->payerName);
        $this->assertFalse($parsed->isComplete());
    }
}
