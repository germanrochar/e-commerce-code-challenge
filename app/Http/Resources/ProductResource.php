<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->resource;
        assert($product instanceof Product);

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price(),
            'stock' => $product->stock,
            'in_stock' => $product->isInStock(),
            'low_stock' => $product->isLowStock(),
            'weight_kg' => $product->weight_kg,
            'image_url' => $product->imageUrl(),
            'category' => $this->whenLoaded('category', fn () => $product->categorySummary()),
        ];
    }
}
