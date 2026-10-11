<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Running Shoes',
            'sku' => 'rs-001',
            'description' => 'Lightweight running shoes for daily training',
            'category' => 'footwear',
            'price' => '89.99',
            'stock' => '150',
            'weight_kg' => '0.35',
        ], $overrides);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.products.index'))->assertRedirect(route('login'));
    }

    public function test_customers_cannot_access_the_admin_area(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.products.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.products.store'), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_admins_can_list_and_search_products(): void
    {
        Product::factory()->create(['name' => 'Camping Tent', 'sku' => 'CT-005']);
        Product::factory()->create(['name' => 'Yoga Mat', 'sku' => 'YM-015']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.index', ['q' => 'tent']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/products/Index')
                ->has('products.data', 1)
                ->where('products.data.0.sku', 'CT-005')
                ->where('products.meta.total', 1));
    }

    public function test_admins_can_create_a_product_with_normalised_input(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.products.store'), $this->validPayload([
                'name' => '  Running   Shoes ',
                'price' => '$89.99',
            ]))
            ->assertRedirect(route('admin.products.index'));

        $product = Product::query()->sole();
        $this->assertSame('RS-001', $product->sku);
        $this->assertSame('Running Shoes', $product->name);
        $this->assertSame(8999, $product->price_cents);
        $this->assertSame(150, $product->stock);
        $this->assertSame('Footwear', $product->category->name);
    }

    public function test_existing_categories_are_reused_regardless_of_case(): void
    {
        $category = Category::factory()->named('Food & Beverage')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.products.store'), $this->validPayload(['category' => 'food & beverage']));

        $this->assertDatabaseCount('categories', 1);
        $this->assertSame($category->id, Product::query()->sole()->category_id);
    }

    public function test_invalid_products_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.products.store'), $this->validPayload([
                'name' => "<script>alert('xss')</script>",
                'price' => '0.00',
                'stock' => '-5',
                'category' => '',
            ]))
            ->assertSessionHasErrors(['name', 'price', 'stock', 'category']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_sku_must_be_unique_including_deleted_products(): void
    {
        Product::factory()->create(['sku' => 'RS-001'])->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.products.store'), $this->validPayload())
            ->assertSessionHasErrors(['sku' => 'This SKU is already used by another product.']);
    }

    public function test_admins_can_update_a_product_keeping_its_own_sku(): void
    {
        $product = Product::factory()->create(['sku' => 'RS-001']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.products.update', $product), $this->validPayload(['price' => '94.99', 'stock' => '120']))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(9499, $product->price_cents);
        $this->assertSame(120, $product->stock);
    }

    public function test_deleting_a_product_soft_deletes_it(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect();

        $this->assertSoftDeleted($product);
    }
}
