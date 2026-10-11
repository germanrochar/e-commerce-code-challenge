<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogBrowsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_browse_the_catalog(): void
    {
        Product::factory()->count(3)->create();

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('store/Catalog')
                ->has('products.data', 3)
                ->where('filters', ['q' => '', 'category' => null, 'sort' => 'name', 'dir' => 'asc'])
                ->where('auth.user', null));
    }

    public function test_products_are_sorted_alphabetically_in_both_directions(): void
    {
        foreach (['Banana Bread', 'apple pie', 'Cherry Jam'] as $name) {
            Product::factory()->create(['name' => $name]);
        }

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.data.0.name', 'apple pie')
                ->where('products.data.2.name', 'Cherry Jam'));

        $this->get(route('home', ['sort' => 'name', 'dir' => 'desc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.data.0.name', 'Cherry Jam')
                ->where('products.data.2.name', 'apple pie'));
    }

    public function test_products_can_be_sorted_by_price(): void
    {
        Product::factory()->priced(3000)->create(['name' => 'Mid']);
        Product::factory()->priced(1000)->create(['name' => 'Cheap']);
        Product::factory()->priced(9000)->create(['name' => 'Pricey']);

        $this->get(route('home', ['sort' => 'price', 'dir' => 'desc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.data.0.name', 'Pricey')
                ->where('products.data.2.name', 'Cheap'));
    }

    public function test_products_can_be_filtered_by_category_with_counts_per_category(): void
    {
        $shoes = Category::factory()->named('Footwear')->create();
        $books = Category::factory()->named('Books')->create();
        Category::factory()->named('Empty Shelf')->create();
        Product::factory()->count(2)->for($shoes)->create();
        Product::factory()->count(3)->for($books)->create();

        $this->get(route('home', ['category' => 'footwear']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 2)
                ->where('activeCategory', ['name' => 'Footwear', 'slug' => 'footwear'])
                ->where('categories', [
                    ['name' => 'Books', 'slug' => 'books', 'count' => 3],
                    ['name' => 'Footwear', 'slug' => 'footwear', 'count' => 2],
                ]));
    }

    public function test_an_unknown_category_shows_no_products(): void
    {
        Product::factory()->create();

        $this->get(route('home', ['category' => 'does-not-exist']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 0)
                ->where('activeCategory', null));
    }

    public function test_the_catalog_is_paginated_and_keeps_the_query_string(): void
    {
        $category = Category::factory()->named('Books')->create();
        Product::factory()->count(14)->for($category)->create();

        $this->get(route('home', ['category' => 'books', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 2)
                ->where('products.meta.total', 14)
                ->where('products.meta.last_page', 2)
                ->where('products.links.prev', fn (string $url) => str_contains($url, 'category=books')));
    }

    public function test_deleted_products_are_hidden_from_the_catalog(): void
    {
        Product::factory()->create(['name' => 'Visible']);
        Product::factory()->create(['name' => 'Gone'])->delete();

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Visible'));
    }

    public function test_tampered_query_parameters_fall_back_to_safe_defaults(): void
    {
        Product::factory()->create();

        $this->get('/?sort=price_cents;drop&dir=sideways&category[]=x&q[]=y')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters', ['q' => '', 'category' => null, 'sort' => 'name', 'dir' => 'asc']));
    }

    public function test_the_product_page_is_reachable_by_sku_with_related_products(): void
    {
        $category = Category::factory()->named('Footwear')->create();
        $product = Product::factory()->for($category)->create(['sku' => 'RS-001', 'name' => 'Running Shoes']);
        Product::factory()->count(5)->for($category)->create();
        Product::factory()->create();

        $this->get(route('products.show', 'RS-001'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('store/Product')
                ->where('product.name', 'Running Shoes')
                ->where('product.category.slug', 'footwear')
                ->where('product.image_url', '/images/product-placeholder.svg')
                ->has('related', 4)
                ->where('related', fn ($related) => collect($related)->every(fn ($item) => $item['category']['slug'] === 'footwear' && $item['sku'] !== 'RS-001')));
    }

    public function test_deleted_or_unknown_products_return_not_found(): void
    {
        Product::factory()->create(['sku' => 'OLD-1'])->delete();

        $this->get(route('products.show', 'OLD-1'))->assertNotFound();
        $this->get(route('products.show', 'NOPE'))->assertNotFound();
    }

    public function test_admins_are_flagged_in_shared_props(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.isAdmin', true));

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.isAdmin', false));
    }
}
