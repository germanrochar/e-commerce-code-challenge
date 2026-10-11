<?php

namespace Tests\Feature\Catalog;

use App\Catalog\CatalogCache;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_repeated_catalog_request_does_not_rerun_the_search_query(): void
    {
        Product::factory()->count(3)->create();

        $this->get(route('home'))->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 3));

        $searchQueries = collect(DB::getQueryLog())->filter(
            fn (array $query): bool => str_contains($query['query'], 'MATCH')
                || (str_contains($query['query'], 'from `products`') && str_contains($query['query'], 'order by')),
        );

        $this->assertCount(0, $searchQueries);
    }

    public function test_creating_or_renaming_a_product_busts_the_catalog_cache(): void
    {
        $product = Product::factory()->create(['name' => 'Old Name']);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('products.data.0.name', 'Old Name'));

        $product->update(['name' => 'New Name']);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('products.data.0.name', 'New Name'));
    }

    public function test_deleting_a_product_busts_the_catalog_cache(): void
    {
        $product = Product::factory()->create(['name' => 'Gone Soon']);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1));

        $product->delete();

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
    }

    public function test_a_stock_only_change_does_not_bump_the_catalog_version(): void
    {
        $product = Product::factory()->withStock(8)->create();
        $cache = app(CatalogCache::class);
        $version = $cache->version();

        $this->get(route('home'))->assertOk();

        Product::query()->whereKey($product->id)->decrement('stock', 3);

        $this->assertSame($version, $cache->version());

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('products.data.0.stock', 5));
    }
}
