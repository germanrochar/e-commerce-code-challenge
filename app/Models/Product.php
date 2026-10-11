<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['category_id', 'sku', 'name', 'description', 'price_cents', 'stock', 'weight_kg'])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const int LOW_STOCK_THRESHOLD = 10;

    public const string PLACEHOLDER_IMAGE = '/images/product-placeholder.svg';

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'stock' => 'integer',
            'weight_kg' => 'decimal:3',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function price(): Money
    {
        return Money::fromCents($this->price_cents);
    }

    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    public function isLowStock(): bool
    {
        return $this->stock > 0 && $this->stock <= self::LOW_STOCK_THRESHOLD;
    }

    public function imageUrl(): string
    {
        return self::PLACEHOLDER_IMAGE;
    }

    public function categorySummary(): ?array
    {
        $category = $this->category;

        return $category instanceof Category
            ? ['name' => $category->name, 'slug' => $category->slug]
            : null;
    }
}
