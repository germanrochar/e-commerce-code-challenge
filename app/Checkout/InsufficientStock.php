<?php

namespace App\Checkout;

use RuntimeException;

class InsufficientStock extends RuntimeException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct("Not enough stock for product [{$productId}].");
    }
}
