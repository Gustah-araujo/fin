<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class TransferSameAccountException extends Exception
{
    protected $message = 'Conta de origem e destino devem ser diferentes.';
}
