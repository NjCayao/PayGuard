<?php

namespace App\Services\Demo;

use App\Models\Business;
use App\Models\Verification;
use App\Services\Meta\MetaModelException;
use App\Services\Meta\PaymentQueryParser;
use App\Services\Meta\ScreenshotReader;
use App\Services\Verification\CashierReplies;
use App\Services\Verification\VerificationService;
use App\Services\WhatsApp\MediaStore;
use Illuminate\Support\Str;

class TrialRunner
{
    public function __construct(
        private TrialSession $session,
        private ScreenshotReader $reader,
        private PaymentQueryParser $parser,
        private VerificationService $verifications,
        private CashierReplies $replies,
        private MediaStore $media,
        private DemoReceiptImage $images,
    ) {}

    public function runImage(Business $business, string $trialKey, string $bytes, string $mime): TrialResult
    {
        $this->session->ensurePayments($business, $trialKey);

        $verification = $this->create($business, $trialKey, 'image');

        $this->media->store($verification, $bytes, $mime);

        try {
            $this->reader->read($verification)->applyTo($verification);
        } catch (MetaModelException) {
            $verification->update([
                'status' => 'error',
                'reason' => 'No se pudo leer la captura.',
                'reason_code' => 'error',
            ]);

            return TrialResult::fromVerification($verification->fresh(), $this->replies, $business);
        }

        $this->verifications->analyze($verification->fresh(), allowPending: false);

        return TrialResult::fromVerification($verification->fresh(), $this->replies, $business);
    }

    public function runQuery(Business $business, string $trialKey, string $text): TrialResult
    {
        $this->session->ensurePayments($business, $trialKey);

        $verification = $this->create($business, $trialKey, 'text', [
            'extracted' => ['raw_text' => $text],
        ]);

        try {
            $this->parser->parse($text)->applyTo($verification);
        } catch (MetaModelException) {
            $verification->update([
                'status' => 'error',
                'reason' => 'No se pudo revisar tu consulta.',
                'reason_code' => 'error',
            ]);

            return TrialResult::fromVerification($verification->fresh(), $this->replies, $business);
        }

        $verification->refresh();

        $query = $verification->extracted['query'] ?? null;

        if (! is_array($query)
            || ($query['is_payment_query'] ?? false) !== true
            || ($query['amount'] ?? null) === null
        ) {
            $verification->update([
                'status' => 'error',
                'reason' => 'Mensaje de texto: se envió ayuda.',
                'reason_code' => 'text_help',
            ]);

            return TrialResult::help(
                $verification->fresh(),
                (string) config('payguard.whatsapp.replies.text_help')
            );
        }

        $this->verifications->analyze($verification, allowPending: false);

        return TrialResult::fromVerification($verification->fresh(), $this->replies, $business);
    }

    public function sample(Business $business, string $trialKey, string $kind): string
    {
        $payment = $this->session->ensurePayments($business, $trialKey)->first();

        abort_if($payment === null, 500, 'La cuenta demo no tiene pagos de prueba.');

        $this->session->refresh($payment);

        $shown = $kind === 'fake'
            ? (string) config('payguard.demo.trial.fake_amount')
            : (string) $payment->amount;

        return $this->images->generate(
            'S/ '.number_format((float) $shown, 2, '.', ','),
            $business->yape_holder_name,
            $payment->received_at->copy()->setTimezone($business->timezone)->locale('es')->isoFormat('D MMM YYYY, h:mm a'),
            $payment->security_code,
            $payment->operation_code,
        );
    }

    private function create(Business $business, string $trialKey, string $inputType, array $extra = []): Verification
    {
        $cashier = $business->cashiers()->where('is_active', true)->orderBy('id')->first();

        abort_if($cashier === null, 500, 'La cuenta demo no tiene cajeros.');

        return Verification::create(array_merge([
            'business_id' => $business->id,
            'cashier_id' => $cashier->id,
            'wa_message_id' => 'trial-'.Str::uuid(),
            'source' => 'trial',
            'trial_key' => $trialKey,
            'input_type' => $inputType,
            'status' => 'processing',
        ], $extra));
    }
}
