<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\ReceivedPayment;
use App\Services\Support\Amounts;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class SimulatePayment extends Command
{
    protected $signature = 'payguard:simulate-payment
        {--business= : Business id that receives the payment}
        {--amount= : Amount like 50.00}
        {--payer=Cliente Simulado : Name of the payer}
        {--wallet=yape : Wallet: yape or plin}
        {--security-code= : 3-digit Yape security code}
        {--operation-code= : Operation number}
        {--minutes-ago=0 : Minutes before now for the payment time}
        {--at= : Exact payment time "Y-m-d H:i" in the business timezone}
        {--device= : Device id (defaults to the first device of the business)}';

    protected $description = 'Insert a fake received payment for end-to-end testing';

    public function handle(): int
    {
        if (app()->environment('production') && ! config('payguard.simulator.enabled')) {
            $this->error('The simulator is disabled in production. Set PAYGUARD_SIMULATOR_ENABLED=true to enable it on purpose.');

            return self::FAILURE;
        }

        $business = Business::find($this->option('business'));

        if ($business === null) {
            $this->error('Business not found. Pass --business=ID.');

            return self::FAILURE;
        }

        $amount = Amounts::normalize($this->option('amount'));

        if ($amount === null) {
            $this->error('Invalid --amount. Use a positive number like 50.00.');

            return self::FAILURE;
        }

        if (! in_array($this->option('wallet'), ['yape', 'plin'], true)) {
            $this->error('Invalid --wallet. Use yape or plin.');

            return self::FAILURE;
        }

        $securityCode = $this->option('security-code');

        if ($securityCode !== null && preg_match('/^\d{3}$/', $securityCode) !== 1) {
            $this->error('Invalid --security-code. Use 3 digits.');

            return self::FAILURE;
        }

        $minutesAgo = (int) $this->option('minutes-ago');

        if ($minutesAgo < 0) {
            $this->error('Invalid --minutes-ago. Use 0 or more.');

            return self::FAILURE;
        }

        $at = $this->option('at');

        if ($at !== null && $minutesAgo !== 0) {
            $this->error('Use either --at or --minutes-ago, not both.');

            return self::FAILURE;
        }

        try {
            $receivedAt = $at !== null
                ? Carbon::createFromFormat('Y-m-d H:i', $at, $business->timezone)->setTimezone('UTC')
                : now()->subMinutes($minutesAgo);
        } catch (Throwable) {
            $this->error('Invalid --at. Use "Y-m-d H:i", for example "2026-10-28 15:20".');

            return self::FAILURE;
        }

        $device = $this->option('device') !== null
            ? $business->devices()->find($this->option('device'))
            : $business->devices()->orderBy('id')->first();

        if ($device === null) {
            $this->error('No device found for this business. Link the Android app first.');

            return self::FAILURE;
        }

        $payer = mb_substr(trim((string) $this->option('payer')) === '' ? 'Cliente Simulado' : trim((string) $this->option('payer')), 0, 120);

        $payment = ReceivedPayment::create([
            'business_id' => $business->id,
            'device_id' => $device->id,
            'wallet' => $this->option('wallet'),
            'source_package' => 'payguard.simulator',
            'amount' => $amount,
            'payer_name' => $payer,
            'security_code' => $securityCode,
            'operation_code' => $this->option('operation-code'),
            'received_at' => $receivedAt,
            'raw_text' => "Simulated payment: {$payer} paid S/ {$amount}.",
            'parsed' => true,
            'idempotency_key' => 'sim-'.Str::uuid(),
        ]);

        $device->update(['last_payment_at' => now(), 'last_seen_at' => now()]);

        $this->info("Simulated payment #{$payment->id}: S/ {$amount} from {$payer} at ".
            $receivedAt->copy()->setTimezone($business->timezone)->format('Y-m-d H:i')." ({$business->timezone}).");

        return self::SUCCESS;
    }
}
