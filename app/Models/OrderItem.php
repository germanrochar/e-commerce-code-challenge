<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'product_id', 'sku', 'name', 'unit_price_cents', 'quantity', 'line_total_cents'])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'unit_price_cents' => 'integer',
            'quantity' => 'integer',
            'line_total_cents' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function unitPrice(): Money
    {
        return Money::fromCents($this->unit_price_cents);
    }

    public function lineTotal(): Money
    {
        return Money::fromCents($this->line_total_cents);
    }
}
