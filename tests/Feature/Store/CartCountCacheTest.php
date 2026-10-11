<?php

namespace Tests\Feature\Store;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartCountCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shared_cart_count_is_cached_between_store_requests(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('cartCount', 2));

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('cartCount', 2));

        $cartQueries = collect(DB::getQueryLog())->filter(
            fn (array $query): bool => str_contains($query['query'], 'cart_items'),
        );

        $this->assertCount(0, $cartQueries);
    }

    public function test_adding_to_the_cart_busts_the_cached_count(): void
    {
        $user = User::factory()->create();
        $first = Product::factory()->create();
        $second = Product::factory()->create();

        $this->actingAs($user)->post(route('cart.items.store'), [
            'product_id' => $first->id,
        ]);
        $this->actingAs($user)->get(route('home'));

        $this->actingAs($user)->post(route('cart.items.store'), [
            'product_id' => $second->id,
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('cartCount', 2));
    }

    public function test_admin_pages_do_not_query_the_cart_count(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('cart.items.store'), [
            'product_id' => Product::factory()->create()->id,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('cartCount', 0));

        $cartQueries = collect(DB::getQueryLog())->filter(
            fn (array $query): bool => str_contains($query['query'], 'cart_items'),
        );

        $this->assertCount(0, $cartQueries);
    }
}
