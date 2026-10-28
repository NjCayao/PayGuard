<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoSeeder;

class DemoSeedCommand extends DemoCommand
{
    protected $signature = 'payguard:demo-seed';

    protected $description = 'Create or refresh the demo account data';

    public function handle(DemoSeeder $seeder): int
    {
        if ($this->guard() !== null) {
            return self::FAILURE;
        }

        $summary = $seeder->run();

        $this->info("Demo account ready: {$summary['payments']} payments, ".
            "{$summary['verifications']} verifications, {$summary['alerts']} alerts.");

        return self::SUCCESS;
    }
}
