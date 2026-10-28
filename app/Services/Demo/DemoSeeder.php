<?php

namespace App\Services\Demo;

use App\Models\Business;
use App\Models\Cashier;
use App\Models\Device;
use App\Models\OwnerAlert;
use App\Models\ReceivedPayment;
use App\Models\User;
use App\Models\Verification;
use App\Services\Verification\CashierReplies;
use App\Services\Verification\VerificationOutcome;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoSeeder
{
    private const HOLDER = 'Luz Marina Torres';

    private const OWNER_NAME = 'Dueño Demo';

    private const OWNER_WHATSAPP = '51999888001';

    private const DEVICE_NAME = 'Xiaomi Redmi 13C';

    public function __construct(
        private CashierReplies $replies,
        private DemoReceiptImage $images,
    ) {}

    /**
     * @return array{business_id: int, payments: int, verifications: int, alerts: int}
     */
    public function run(): array
    {
        [$business, $verifications] = DB::transaction(function () {
            $business = $this->findOrCreateBusiness();
            $this->wipe($business);

            $cashiers = $this->seedCashiers($business);
            $device = $this->seedDevice($business);
            $payments = $this->seedPayments($business, $device);
            $verifications = $this->seedVerifications($business, $cashiers, $payments);
            $this->seedAlerts($business, $verifications);

            return [$business, $verifications];
        });

        $this->storeImages($business, $verifications);

        return [
            'business_id' => $business->id,
            'payments' => ReceivedPayment::forBusiness($business->id)->count(),
            'verifications' => Verification::forBusiness($business->id)->count(),
            'alerts' => OwnerAlert::forBusiness($business->id)->count(),
        ];
    }

    private function findOrCreateBusiness(): Business
    {
        $email = (string) config('payguard.demo.email');
        $password = (string) config('payguard.demo.password');

        $user = User::where('email', $email)->first();

        if ($user !== null) {
            $business = $user->business;
            $business->update([
                'name' => (string) config('payguard.demo.business_name'),
                'owner_whatsapp' => self::OWNER_WHATSAPP,
                'yape_holder_name' => self::HOLDER,
                'match_window_minutes' => config('payguard.defaults.match_window_minutes'),
                'recheck_minutes' => config('payguard.defaults.recheck_minutes'),
                'timezone' => 'America/Lima',
                'is_demo' => true,
            ]);
            $user->update(['name' => self::OWNER_NAME, 'password' => $password]);

            return $business->fresh();
        }

        $business = Business::create([
            'name' => (string) config('payguard.demo.business_name'),
            'owner_whatsapp' => self::OWNER_WHATSAPP,
            'yape_holder_name' => self::HOLDER,
            'match_window_minutes' => config('payguard.defaults.match_window_minutes'),
            'recheck_minutes' => config('payguard.defaults.recheck_minutes'),
            'timezone' => 'America/Lima',
            'is_demo' => true,
        ]);

        $business->users()->create([
            'name' => self::OWNER_NAME,
            'email' => $email,
            'password' => $password,
        ]);

        return $business;
    }

    private function wipe(Business $business): void
    {
        $media = Verification::forBusiness($business->id)
            ->whereNotNull('media_path')
            ->pluck('media_path')
            ->all();

        if ($media !== []) {
            Storage::disk(config('payguard.whatsapp.media_disk'))->delete(array_unique($media));
        }

        OwnerAlert::forBusiness($business->id)->delete();
        Verification::forBusiness($business->id)->delete();
        ReceivedPayment::forBusiness($business->id)->delete();
        Cashier::forBusiness($business->id)->delete();

        $deviceIds = Device::forBusiness($business->id)->pluck('id')->all();

        DB::table('personal_access_tokens')
            ->where('tokenable_type', Device::class)
            ->whereIn('tokenable_id', $deviceIds)
            ->delete();

        Device::forBusiness($business->id)->delete();
    }

    /**
     * @return array<string, Cashier>
     */
    private function seedCashiers(Business $business): array
    {
        return [
            'rosa' => Cashier::create([
                'business_id' => $business->id,
                'name' => 'Rosa Quispe',
                'whatsapp' => '51999888011',
                'is_active' => true,
            ]),
            'jorge' => Cashier::create([
                'business_id' => $business->id,
                'name' => 'Jorge Huamán',
                'whatsapp' => '51999888012',
                'is_active' => true,
            ]),
        ];
    }

    private function seedDevice(Business $business): Device
    {
        return Device::create([
            'business_id' => $business->id,
            'name' => self::DEVICE_NAME,
            'install_id' => 'demo-'.Str::uuid(),
            'last_seen_at' => now(),
            'last_payment_at' => now(),
        ]);
    }

    /**
     * @return array<string, ReceivedPayment>
     */
    private function seedPayments(Business $business, Device $device): array
    {
        $rows = [
            'p1' => ['50.00', 'Juan Pérez', '123', 'DEMO-1001', 0, 9, 12],
            'p2' => ['35.50', 'Carmen Ríos', '456', 'DEMO-1002', 0, 9, 40],
            'p3' => ['20.00', 'Luis García', null, null, 0, 10, 5],
            'p4' => ['50.00', 'Ana Torres', '789', 'DEMO-1003', 0, 11, 20],
            'p5' => ['80.00', 'Pedro Sánchez', '321', 'DEMO-1004', 0, 12, 30],
            'p6' => ['15.00', 'Rosa Díaz', null, null, 1, 18, 10],
            'p7' => ['120.90', 'Miguel Vargas', '654', 'DEMO-1005', 1, 17, 40],
            'p8' => ['25.00', 'Elena Paredes', '111', 'DEMO-1006', 0, 13, 15],
            'p9' => ['25.00', 'Raúl Mendoza', '222', 'DEMO-1007', 0, 13, 18],
            'p10' => ['60.00', 'Sofía Ramos', '333', 'DEMO-1008', 0, 8, 5],
            'p11' => ['45.00', 'Diego Flores', null, null, 0, 14, 2],
            'p12' => ['10.00', 'Lucía Castro', '999', 'DEMO-1009', 0, 14, 20],
        ];

        $payments = [];

        foreach ($rows as $key => [$amount, $payer, $code, $operation, $daysAgo, $hour, $minute]) {
            $receivedAt = $this->at($daysAgo, $hour, $minute);

            $raw = "Yape! {$payer} te envió un pago por S/ {$amount}.";

            if ($code !== null) {
                $raw .= " Cód. de seguridad: {$code}.";
            }

            $payments[$key] = ReceivedPayment::create([
                'business_id' => $business->id,
                'device_id' => $device->id,
                'wallet' => 'yape',
                'source_package' => 'com.bcp.innovacxion.yapeapp',
                'amount' => $amount,
                'payer_name' => $payer,
                'security_code' => $code,
                'operation_code' => $operation,
                'received_at' => $receivedAt,
                'raw_text' => $raw,
                'parsed' => true,
                'idempotency_key' => 'demo-'.Str::uuid(),
            ]);

            $this->stamp($payments[$key], $receivedAt);
        }

        return $payments;
    }

    /**
     * @param  array<string, Cashier>  $cashiers
     * @param  array<string, ReceivedPayment>  $payments
     * @return array<string, Verification>
     */
    private function seedVerifications(Business $business, array $cashiers, array $payments): array
    {
        $specs = [
            'v1' => [
                'cashier' => 'rosa', 'input_type' => 'image', 'amount' => '50.00',
                'recipient_name' => self::HOLDER, 'security_code' => '123',
                'operation_code' => 'DEMO-1001', 'paid_at' => $this->at(0, 9, 12),
                'created_at' => $this->at(0, 9, 14), 'outcome' => ['verified', 'verified', [], 'p1'],
            ],
            'v2' => [
                'cashier' => 'jorge', 'input_type' => 'image', 'amount' => '35.50',
                'recipient_name' => self::HOLDER, 'security_code' => '456',
                'operation_code' => 'DEMO-1002', 'paid_at' => $this->at(0, 9, 40),
                'created_at' => $this->at(0, 9, 42), 'outcome' => ['verified', 'verified', [], 'p2'],
            ],
            'v3' => [
                'cashier' => 'rosa', 'input_type' => 'image', 'amount' => '50.00',
                'recipient_name' => self::HOLDER, 'security_code' => '000',
                'operation_code' => 'DEMO-1010', 'paid_at' => $this->at(0, 11, 21),
                'created_at' => $this->at(0, 11, 23), 'outcome' => ['mismatch', 'mismatch_code', [], null],
            ],
            'v4' => [
                'cashier' => 'jorge', 'input_type' => 'image', 'amount' => '80.00',
                'recipient_name' => 'Otro Negocio', 'security_code' => '321',
                'operation_code' => 'DEMO-1004', 'paid_at' => $this->at(0, 12, 31),
                'created_at' => $this->at(0, 12, 33),
                'edit_signals' => ['El nombre del destinatario usa otra fuente.'],
                'outcome' => ['mismatch', 'mismatch_recipient', [], null],
            ],
            'v5' => [
                'cashier' => 'rosa', 'input_type' => 'image', 'amount' => '79.00',
                'recipient_name' => self::HOLDER, 'security_code' => null,
                'operation_code' => null, 'paid_at' => $this->at(0, 12, 32),
                'created_at' => $this->at(0, 12, 35),
                'outcome' => ['mismatch', 'mismatch_amount', ['amount' => '80.00', 'expected' => '79.00'], null],
            ],
            'v6' => [
                'cashier' => 'jorge', 'input_type' => 'image', 'amount' => '200.00',
                'recipient_name' => self::HOLDER, 'security_code' => null,
                'operation_code' => null, 'paid_at' => $this->at(0, 10, 30),
                'created_at' => $this->at(0, 10, 34), 'outcome' => ['not_found', 'not_found', [], null],
            ],
            'v7' => [
                'cashier' => 'rosa', 'input_type' => 'image', 'amount' => '30.00',
                'recipient_name' => self::HOLDER, 'security_code' => null,
                'operation_code' => null, 'paid_at' => $this->at(1, 15, 0),
                'created_at' => $this->at(1, 18, 30), 'outcome' => ['not_found', 'stale', [], null],
            ],
            'v8' => [
                'cashier' => 'rosa', 'input_type' => 'image', 'amount' => '50.00',
                'recipient_name' => self::HOLDER, 'security_code' => '123',
                'operation_code' => 'DEMO-1001', 'paid_at' => $this->at(0, 9, 12),
                'created_at' => $this->at(0, 9, 50), 'outcome' => ['reused', 'reused', [], null],
                'same_image_as' => 'v1',
            ],
            'v9' => [
                'cashier' => 'jorge', 'input_type' => 'text', 'amount' => '25.00',
                'created_at' => $this->at(0, 13, 20),
                'extracted' => [
                    'raw_text' => '¿Llegó un yape de 25 soles?',
                    'query' => ['is_payment_query' => true, 'wallet' => 'yape', 'amount' => '25.00', 'payer_name' => null],
                ],
                'outcome' => ['mismatch', 'multiple_matches', [
                    'count' => '2',
                    'matches' => [
                        ['payer' => 'Elena Paredes', 'received_at' => $payments['p8']->received_at->toISOString()],
                        ['payer' => 'Raúl Mendoza', 'received_at' => $payments['p9']->received_at->toISOString()],
                    ],
                ], null],
            ],
            'v10' => [
                'cashier' => 'rosa', 'input_type' => 'audio', 'amount' => '50.00',
                'created_at' => $this->at(0, 13, 45),
                'transcript' => '¿Llegó un yape de cincuenta soles de Juan?',
                'extracted' => [
                    'asr' => ['audio_duration_ms' => 4200],
                    'query' => ['is_payment_query' => true, 'wallet' => 'yape', 'amount' => '50.00', 'payer_name' => 'Juan'],
                ],
                'outcome' => ['reused', 'already_confirmed', [
                    'confirmed_at' => $payments['p1']->received_at->toISOString(),
                ], null],
            ],
            'v11' => [
                'cashier' => 'jorge', 'input_type' => 'text', 'amount' => '60.00',
                'created_at' => $this->at(0, 8, 10),
                'extracted' => [
                    'raw_text' => '¿Llegó un yape de 60 de Sofía?',
                    'query' => ['is_payment_query' => true, 'wallet' => 'yape', 'amount' => '60.00', 'payer_name' => 'Sofía'],
                ],
                'outcome' => ['verified', 'verified', [], 'p10'],
            ],
            'v12' => [
                'cashier' => 'rosa', 'input_type' => 'image', 'amount' => '45.50',
                'recipient_name' => self::HOLDER, 'security_code' => null,
                'operation_code' => null, 'paid_at' => now()->subMinutes(3),
                'created_at' => now()->subMinute(), 'recheck_until' => now()->addMinutes(4),
                'outcome' => ['retry', 'pending', [], null],
            ],
            'v13' => [
                'cashier' => 'jorge', 'input_type' => 'image', 'amount' => '33.00',
                'recipient_name' => self::HOLDER, 'security_code' => null,
                'operation_code' => null, 'paid_at' => $this->at(1, 19, 0),
                'created_at' => $this->at(1, 19, 5), 'outcome' => ['not_found', 'offline', [], null],
            ],
        ];

        $verifications = [];
        $number = 0;

        foreach ($specs as $key => $spec) {
            $number++;
            $verifications[$key] = $this->seedVerification($business, $cashiers, $payments, $key, $number, $spec);
        }

        return $verifications;
    }

    private function seedVerification(
        Business $business,
        array $cashiers,
        array $payments,
        string $key,
        int $number,
        array $spec,
    ): Verification {
        $payment = ($spec['outcome'][3] ?? null) !== null ? $payments[$spec['outcome'][3]] : null;

        $outcome = new VerificationOutcome(
            status: $spec['outcome'][0],
            reasonCode: $spec['outcome'][1],
            params: $spec['outcome'][2],
            receivedPaymentId: $payment?->id,
        );

        $extracted = $spec['extracted'] ?? $this->imageExtracted($spec);

        $draft = (new Verification)->forceFill([
            'wallet' => 'yape',
            'amount' => $spec['amount'],
            'paid_at' => $spec['paid_at'] ?? null,
            'created_at' => $spec['created_at'],
            'edit_signals' => $spec['edit_signals'] ?? null,
        ]);
        $draft->setRelation('business', $business);

        $rendered = $this->replies->render($draft, $outcome, $payment);
        $status = $outcome->status === 'retry' ? 'pending' : $outcome->status;

        $verification = Verification::create([
            'business_id' => $business->id,
            'cashier_id' => $cashiers[$spec['cashier']]->id,
            'wa_message_id' => "wamid.demo.{$key}",
            'input_type' => $spec['input_type'],
            'transcript' => $spec['transcript'] ?? null,
            'extracted' => $extracted,
            'wallet' => 'yape',
            'amount' => $spec['amount'],
            'recipient_name' => $spec['recipient_name'] ?? null,
            'security_code' => $spec['security_code'] ?? null,
            'paid_at' => $spec['paid_at'] ?? null,
            'operation_code' => $spec['operation_code'] ?? null,
            'edit_signals' => $spec['edit_signals'] ?? null,
            'status' => $status,
            'reason' => $rendered['reason'],
            'reason_code' => $outcome->reasonCode,
            'received_payment_id' => $payment?->id,
            'recheck_until' => $spec['recheck_until'] ?? null,
            'ai_ms' => 900 + ($number * 113) % 1800,
        ]);

        $this->stamp($verification, $spec['created_at']);

        return $verification;
    }

    private function imageExtracted(array $spec): array
    {
        $local = $spec['paid_at']->copy()->setTimezone('America/Lima');

        return [
            'wallet' => 'yape',
            'amount' => $spec['amount'],
            'recipient_name' => $spec['recipient_name'],
            'paid_date' => $local->toDateString(),
            'paid_time' => $local->format('H:i'),
            'security_code' => $spec['security_code'],
            'operation_code' => $spec['operation_code'],
            'edit_signals' => $spec['edit_signals'] ?? [],
        ];
    }

    /**
     * @param  array<string, Verification>  $verifications
     */
    private function seedAlerts(Business $business, array $verifications): void
    {
        $rows = [
            ['v3', 'delivered', 'wamid.demo.alert.1', $this->at(0, 11, 24)],
            ['v8', 'read', 'wamid.demo.alert.2', $this->at(0, 9, 52)],
            ['v4', 'sent', 'wamid.demo.alert.3', $this->at(0, 12, 34)],
        ];

        foreach ($rows as [$key, $status, $waMessageId, $createdAt]) {
            $alert = OwnerAlert::create([
                'business_id' => $business->id,
                'verification_id' => $verifications[$key]->id,
                'to' => $business->owner_whatsapp,
                'template' => (string) config('payguard.whatsapp.alert_template'),
                'reason' => $verifications[$key]->reason,
                'wa_message_id' => $waMessageId,
                'status' => $status,
                'error' => null,
            ]);

            $this->stamp($alert, $createdAt);
        }
    }

    private function stamp(Model $model, Carbon $at): void
    {
        $model->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }

    /**
     * @param  array<string, Verification>  $verifications
     */
    private function storeImages(Business $business, array $verifications): void
    {
        $disk = Storage::disk(config('payguard.whatsapp.media_disk'));
        $rendered = [];

        foreach ($verifications as $key => $verification) {
            if ($verification->input_type !== 'image') {
                continue;
            }

            $source = $this->imageSource($key, $verifications);

            if (! isset($rendered[$source])) {
                $original = $verifications[$source];
                $rendered[$source] = $this->images->generate(
                    'S/ '.number_format((float) $original->amount, 2, '.', ','),
                    $original->recipient_name,
                    $original->paid_at->copy()->setTimezone('America/Lima')->locale('es')->isoFormat('D MMM YYYY, h:mm a'),
                    $original->security_code,
                    $original->operation_code,
                );
            }

            $path = "whatsapp/{$business->id}/{$verification->id}.png";

            $disk->put($path, $rendered[$source]);

            $verification->update([
                'media_path' => $path,
                'media_sha256' => hash('sha256', $rendered[$source]),
            ]);
        }
    }

    private function imageSource(string $key, array $verifications): string
    {
        // v8 is the same screenshot as v1 sent again, so it reuses its bytes.
        if ($key === 'v8' && isset($verifications['v1'])) {
            return 'v1';
        }

        return $key;
    }

    private function at(int $daysAgo, int $hour, int $minute): Carbon
    {
        return now('America/Lima')->subDays($daysAgo)->setTime($hour, $minute)->setTimezone('UTC');
    }
}
