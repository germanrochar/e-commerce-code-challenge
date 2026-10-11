<?php

namespace App\Cart;

use App\Models\Product;
use App\Support\Money;

final readonly class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
    ) {}

    public function lineTotal(): Money
    {
        return $this->product->price()->multiply($this->quantity);
    }
}
