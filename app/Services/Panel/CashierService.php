<?php

namespace App\Services\Panel;

use App\Exceptions\CannotDeleteCashier;
use App\Models\Cashier;

class CashierService
{
    public function delete(Cashier $cashier): void
    {
        if ($cashier->verifications()->exists()) {
            throw new CannotDeleteCashier;
        }

        $cashier->delete();
    }
}
