<?php

namespace App\Observers;

use App\Catalog\CatalogCache;
use App\Models\Product;

class ProductObserver
{
    private const array CATALOG_FIELDS = [
        'name',
        'sku',
        'description',
        'price_cents',
        'category_id',
        'weight_kg',
    ];

    public function __construct(private readonly CatalogCache $cache) {}

    public function created(Product $product): void
    {
        $this->cache->bump();
    }

    public function updated(Product $product): void
    {
        if ($product->wasChanged(self::CATALOG_FIELDS)) {
            $this->cache->bump();
        }
    }

    public function deleted(Product $product): void
    {
        $this->cache->bump();
    }

    public function restored(Product $product): void
    {
        $this->cache->bump();
    }

    public function forceDeleted(Product $product): void
    {
        $this->cache->bump();
    }
}
