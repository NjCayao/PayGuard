<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoSeeder;

class DemoResetCommand extends DemoCommand
{
    protected $signature = 'payguard:demo-reset';

    protected $description = 'Reset the demo account to its initial data (creates it if missing)';

    public function handle(DemoSeeder $seeder): int
    {
        if ($this->guard() !== null) {
            return self::FAILURE;
        }

        $summary = $seeder->run();

        $this->info("Demo account reset: {$summary['payments']} payments, ".
            "{$summary['verifications']} verifications, {$summary['alerts']} alerts.");

        return self::SUCCESS;
    }
}
