<?php

namespace App\Actions\Products;

use App\Models\Category;
use App\Models\Product;
use App\Support\Money;

class SaveProduct
{
    public function handle(array $validated, ?Product $product = null): Product
    {
        $product ??= new Product;

        $product->fill([
            'category_id' => Category::findOrCreateByName($validated['category'])->getKey(),
            'sku' => $validated['sku'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price_cents' => Money::fromDecimal((string) $validated['price'])->cents,
            'stock' => (int) $validated['stock'],
            'weight_kg' => $validated['weight_kg'] ?? null,
        ])->save();

        return $product;
    }
}
