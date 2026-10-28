<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\ReceivedPayment;
use App\Services\Payments\NotificationParserResolver;
use Illuminate\Console\Command;

class ReparsePayments extends Command
{
    protected $signature = 'payguard:reparse-payments
        {--dry-run : Show what would change without saving}
        {--business= : Only reparse payments of this business id}
        {--limit= : Max payments to process}';

    protected $description = 'Reparse received payments that were stored as not parsed';

    public function handle(NotificationParserResolver $resolver): int
    {
        $businessId = $this->option('business');

        if ($businessId !== null && Business::find($businessId) === null) {
            $this->error('Business not found. Pass --business=ID.');

            return self::FAILURE;
        }

        $limit = $this->option('limit');

        if ($limit !== null && ((string) (int) $limit !== (string) $limit || (int) $limit < 1)) {
            $this->error('Invalid --limit. Use 1 or more.');

            return self::FAILURE;
        }

        $payments = ReceivedPayment::where('parsed', false)
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->orderBy('id')
            ->when($limit !== null, fn ($query) => $query->limit((int) $limit))
            ->get();

        $dryRun = (bool) $this->option('dry-run');
        $reparsed = 0;
        $incomplete = 0;
        $unreadable = 0;
        $skipped = 0;

        foreach ($payments as $payment) {
            $parser = $resolver->forPackage($payment->source_package);

            if ($parser === null) {
                $skipped++;

                continue;
            }

            $parsed = $parser->parse($payment->raw_text);

            if ($parsed === null) {
                $unreadable++;

                continue;
            }

            // Never wipe a field the new rules cannot read; keep the stored value.
            $amount = $parsed->amount ?? $payment->amount;
            $payer = $parsed->payerName ?? $payment->payer_name;
            $complete = $amount !== null && $payer !== null;

            if ($dryRun) {
                $this->line("Payment #{$payment->id} (business {$payment->business_id}): ".
                    "amount {$this->show($payment->amount)} -> {$this->show($amount)}, ".
                    "payer {$this->show($payment->payer_name)} -> {$this->show($payer)}, ".
                    'parsed '.($complete ? 'true' : 'false'));
            } else {
                $payment->update([
                    'amount' => $amount,
                    'payer_name' => $payer,
                    'security_code' => $parsed->securityCode ?? $payment->security_code,
                    'operation_code' => $parsed->operationCode ?? $payment->operation_code,
                    'parsed' => $complete,
                ]);
            }

            if ($complete) {
                $reparsed++;
            } else {
                $incomplete++;
            }
        }

        $prefix = $dryRun ? 'Would reparse' : 'Reparsed';

        $this->info("{$prefix} {$reparsed} payment(s), {$incomplete} still incomplete, ".
            "{$unreadable} unreadable, {$skipped} skipped.");

        return self::SUCCESS;
    }

    private function show(mixed $value): string
    {
        return $value === null || $value === '' ? '-' : (string) $value;
    }
}
