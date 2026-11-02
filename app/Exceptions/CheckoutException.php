<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule stopped a checkout, voucher or shift action. The message is
 * written for the cashier and is shown as-is.
 */
class CheckoutException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'checkout')
    {
        parent::__construct($message);
    }
}
