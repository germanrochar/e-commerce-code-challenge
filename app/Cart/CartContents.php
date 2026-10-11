<?php

namespace App\Cart;

use App\Support\Money;

final readonly class CartContents
{
    /**
     * @param  list<CartLine>  $lines
     * @param  list<string>  $notices
     */
    public function __construct(
        public array $lines,
        public array $notices = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function count(): int
    {
        return array_sum(array_map(fn (CartLine $line): int => $line->quantity, $this->lines));
    }

    public function total(): Money
    {
        return array_reduce(
            $this->lines,
            fn (Money $total, CartLine $line): Money => $total->add($line->lineTotal()),
            Money::fromCents(0),
        );
    }

    /**
     * @return array<int, int>
     */
    public function quantities(): array
    {
        $quantities = [];

        foreach ($this->lines as $line) {
            $quantities[$line->product->id] = $line->quantity;
        }

        return $quantities;
    }
}
