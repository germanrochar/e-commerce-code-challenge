<?php

namespace App\Catalog;

use App\Models\Product;
use Closure;
use Illuminate\Support\Facades\Cache;

class CatalogCache
{
    public const string VERSION_KEY = 'catalog:version';

    public const int TTL_SECONDS = 60;

    public function version(): int
    {
        return (int) (Cache::get(self::VERSION_KEY) ?? 0);
    }

    public function bump(): void
    {
        Cache::increment(self::VERSION_KEY);
    }

    /**
     * @param  Closure(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    public function rememberPage(ProductSearch $search, int $page, Closure $callback): array
    {
        return Cache::remember($this->pageKey($search, $page), self::TTL_SECONDS, $callback);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function withLiveStock(array $payload): array
    {
        $ids = array_column($payload['products']['data'] ?? [], 'id');

        if ($ids === []) {
            return $payload;
        }

        $stocks = Product::query()->whereKey($ids)->pluck('stock', 'id');

        $payload['products']['data'] = array_map(function (array $product) use ($stocks): array {
            $stock = (int) ($stocks[$product['id']] ?? 0);
            $product['stock'] = $stock;
            $product['in_stock'] = $stock > 0;
            $product['low_stock'] = $stock > 0 && $stock <= Product::LOW_STOCK_THRESHOLD;

            return $product;
        }, $payload['products']['data']);

        return $payload;
    }

    private function pageKey(ProductSearch $search, int $page): string
    {
        $filters = $search->filters();

        return implode(':', [
            'catalog',
            $this->version(),
            'q='.$filters['q'],
            'category='.($filters['category'] ?? ''),
            'sort='.$filters['sort'],
            'dir='.$filters['dir'],
            'page='.$page,
        ]);
    }
}
