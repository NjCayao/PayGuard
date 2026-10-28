<?php

namespace Tests\Unit\Verification;

use App\Models\Business;
use App\Models\ReceivedPayment;
use App\Models\Verification;
use App\Services\Verification\CashierReplies;
use App\Services\Verification\VerificationOutcome;
use Carbon\Carbon;
use Tests\TestCase;

class CashierRepliesTest extends TestCase
{
    public function test_verified_reply(): void
    {
        $rendered = app(CashierReplies::class)->render(
            $this->verification(),
            new VerificationOutcome('verified', 'verified', receivedPaymentId: 7),
            $this->payment(),
        );

        $this->assertSame('Yape recibido: S/ 50.00 de Juan Perez', $rendered['reason']);
        $this->assertSame('✅ Yape recibido: S/ 50.00 de Juan Perez', $rendered['reply']);
    }

    public function test_verified_reply_includes_code_suffix_when_present(): void
    {
        $replies = app(CashierReplies::class);

        $fromPayment = $replies->render(
            $this->verification(),
            new VerificationOutcome('verified', 'verified', receivedPaymentId: 7),
            $this->payment(['security_code' => '944']),
        );

        $this->assertSame('✅ Yape recibido: S/ 50.00 de Juan Perez · Cód. 944', $fromPayment['reply']);

        $fromCapture = $replies->render(
            $this->verification(['security_code' => '123']),
            new VerificationOutcome('verified', 'verified', receivedPaymentId: 7),
            $this->payment(),
        );

        $this->assertSame('✅ Yape recibido: S/ 50.00 de Juan Perez · Cód. 123', $fromCapture['reply']);
    }

    public function test_verified_reply_without_payer_name(): void
    {
        $rendered = app(CashierReplies::class)->render(
            $this->verification(),
            new VerificationOutcome('verified', 'verified', receivedPaymentId: 7),
            new ReceivedPayment([
                'payer_name' => null,
                'received_at' => Carbon::parse('2026-10-28 20:15:00', 'UTC'),
            ]),
        );

        $this->assertSame('✅ Yape recibido: S/ 50.00 de un cliente', $rendered['reply']);
    }

    public function test_offline_reply(): void
    {
        $rendered = app(CashierReplies::class)->render(
            $this->verification(),
            new VerificationOutcome('not_found', 'offline'),
        );

        $this->assertSame(
            '⚠ El celular del negocio no está conectado. No puedo confirmar este pago.',
            $rendered['reply']
        );
    }

    public function test_pending_reply(): void
    {
        $rendered = app(CashierReplies::class)->render(
            $this->verification(),
            new VerificationOutcome('retry', 'pending'),
        );

        $this->assertSame('⏳ Todavía no llega el pago de S/ 50.00. Sigo revisando y te aviso.', $rendered['reply']);
    }

    public function test_already_reviewing_reply(): void
    {
        $rendered = app(CashierReplies::class)->render(
            $this->verification(),
            new VerificationOutcome('pending', 'already_reviewing'),
        );

        $this->assertSame('⏳ Ya estoy revisando esta captura.', $rendered['reply']);
    }

    public function test_reused_and_not_found_replies(): void
    {
        $replies = app(CashierReplies::class);

        $this->assertSame(
            '❌ Esta captura ya se usó antes.',
            $replies->render($this->verification(), new VerificationOutcome('reused', 'reused'))['reply']
        );
        $this->assertSame(
            '❌ No llegó ningún pago de S/ 50.00.',
            $replies->render($this->verification(), new VerificationOutcome('not_found', 'not_found'))['reply']
        );
    }

    public function test_stale_reply_shows_wallet_amount_and_time(): void
    {
        $rendered = app(CashierReplies::class)->render(
            $this->verification(['paid_at' => Carbon::parse('2026-10-20 20:00:00', 'UTC')]),
            new VerificationOutcome('not_found', 'stale'),
        );

        $this->assertSame('❌ No encontré ningún Yape de S/ 50.00 cerca de las 3:00 p. m.', $rendered['reply']);
    }

    public function test_mismatch_amount_reply(): void
    {
        $rendered = app(CashierReplies::class)->render(
            $this->verification(),
            new VerificationOutcome('mismatch', 'mismatch_amount', ['amount' => '10.00', 'expected' => '50.00']),
        );

        $this->assertSame('❌ Llegó un pago parecido de S/ 10.00, no de S/ 50.00.', $rendered['reply']);
    }

    public function test_holder_skipped_suffix(): void
    {
        $rendered = app(CashierReplies::class)->render(
            $this->verification(),
            new VerificationOutcome('verified', 'verified', holderSkipped: true),
            $this->payment(),
        );

        $this->assertStringEndsWith('(destinatario no verificado)', $rendered['reason']);
    }

    public function test_edit_signals_suffix_only_on_final_results(): void
    {
        $replies = app(CashierReplies::class);
        $verification = $this->verification(['edit_signals' => ['Blurry digits']]);

        $final = $replies->render($verification, new VerificationOutcome('verified', 'verified'), $this->payment());
        $pending = $replies->render($verification, new VerificationOutcome('retry', 'pending'));

        $this->assertStringEndsWith('Además, la imagen muestra señales de edición.', $final['reason']);
        $this->assertStringNotContainsString('edición', $pending['reason']);
    }

    public function test_literal_reason_is_copied_with_emoji_and_truncated(): void
    {
        $rendered = app(CashierReplies::class)->render(
            $this->verification(),
            new VerificationOutcome('reused', 'copied', literalReason: 'Esta captura ya se usó antes.'),
        );

        $this->assertSame('❌ Esta captura ya se usó antes.', $rendered['reply']);

        $long = app(CashierReplies::class)->render(
            $this->verification(),
            new VerificationOutcome('reused', 'copied', literalReason: str_repeat('x', 300)),
        );

        $this->assertSame(255, mb_strlen($long['reason']));
    }

    public function test_formats_amounts(): void
    {
        $this->assertSame('S/ 50.00', CashierReplies::formatAmount('50.00'));
        $this->assertSame('S/ 1,234.50', CashierReplies::formatAmount('1234.5'));
    }

    private function verification(array $overrides = []): Verification
    {
        $verification = new Verification(array_merge([
            'wallet' => 'yape',
            'amount' => '50.00',
            'paid_at' => Carbon::parse('2026-10-28 20:15:00', 'UTC'),
            'created_at' => Carbon::parse('2026-10-28 20:16:00', 'UTC'),
        ], $overrides));

        $verification->setRelation('business', new Business(['timezone' => 'America/Lima']));

        return $verification;
    }

    private function payment(array $overrides = []): ReceivedPayment
    {
        return new ReceivedPayment(array_merge([
            'payer_name' => 'Juan Perez',
            'received_at' => Carbon::parse('2026-10-28 20:15:00', 'UTC'),
        ], $overrides));
    }
}
