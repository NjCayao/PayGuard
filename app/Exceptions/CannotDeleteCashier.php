<?php

namespace App\Exceptions;

use Exception;

class CannotDeleteCashier extends Exception
{
    protected $message = 'No se puede eliminar un cajero con verificaciones registradas.';
}
