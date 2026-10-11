<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        $electronics = Category::factory()->named('Electronics')->create();
        $sports = Category::factory()->named('Sports')->create();

        Product::factory()->for($electronics)->create(['sku' => 'BS-021', 'name' => 'Bluetooth Speaker', 'description' => 'Portable waterproof speaker, 10W']);
        Product::factory()->for($electronics)->create(['sku' => 'SS-022', 'name' => 'Shower Speaker', 'description' => 'Bluetooth shower speaker with suction cup']);
        Product::factory()->for($electronics)->create(['sku' => 'WM-042', 'name' => 'Wireless Mouse', 'description' => 'Ergonomic mouse with USB receiver']);
        Product::factory()->for($sports)->create(['sku' => 'YM-015', 'name' => 'Yoga Mat', 'description' => 'Premium non-slip yoga mat']);
        Product::factory()->for($sports)->create(['sku' => 'JR-022', 'name' => 'Jump Rope', 'description' => 'Speed jump rope with ball bearing']);
    }

    protected function tearDown(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            DB::table('products')->truncate();
            DB::table('categories')->truncate();
        });

        parent::tearDown();
    }

    private function skusFor(array $query): array
    {
        $skus = [];

        $this->get(route('home', $query))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$skus) {
                $skus = collect($page->toArray()['props']['products']['data'])->pluck('sku')->all();
            });

        return $skus;
    }

    public function test_it_searches_names_and_descriptions(): void
    {
        $this->assertEqualsCanonicalizing(['BS-021', 'SS-022'], $this->skusFor(['q' => 'speaker']));
        $this->assertSame(['WM-042'], $this->skusFor(['q' => 'ergonomic']));
    }

    public function test_it_matches_word_prefixes_and_requires_every_word(): void
    {
        $this->assertEqualsCanonicalizing(['BS-021', 'SS-022'], $this->skusFor(['q' => 'speak']));
        $this->assertSame(['SS-022'], $this->skusFor(['q' => 'shower speaker']));
    }

    public function test_best_matches_come_first(): void
    {
        $this->assertSame('BS-021', $this->skusFor(['q' => 'bluetooth waterproof speaker'])[0] ?? null);
    }

    public function test_it_finds_products_by_exact_sku(): void
    {
        $this->assertSame(['YM-015'], $this->skusFor(['q' => 'ym-015']));
    }

    public function test_stopwords_and_operators_do_not_break_the_search(): void
    {
        $this->assertSame(['JR-022'], $this->skusFor(['q' => 'the jump rope with']));
        $this->assertSame([], $this->skusFor(['q' => '+-~<>()"*@']));
    }

    public function test_search_combines_with_category_and_updates_counts(): void
    {
        $this->get(route('home', ['q' => 'speed', 'category' => 'sports']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('filters.sort', 'relevance')
                ->where('categories', [['name' => 'Sports', 'slug' => 'sports', 'count' => 1]]));
    }

    public function test_a_search_with_no_matches_returns_an_empty_page(): void
    {
        $this->get(route('home', ['q' => 'submarine']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 0)
                ->where('products.meta.total', 0)
                ->where('categories', []));
    }
}
