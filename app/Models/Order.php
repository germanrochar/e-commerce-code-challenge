<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'status',
    'total_cents',
    'currency',
    'idempotency_key',
    'payment_reference',
    'card_brand',
    'card_last4',
    'payment_failure_reason',
    'paid_at',
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function total(): Money
    {
        return Money::fromCents($this->total_cents, $this->currency);
    }

    public function getRouteKeyName(): string
    {
        return 'idempotency_key';
    }
}
