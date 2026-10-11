<?php

namespace Tests\Feature\Store;

use App\Cart\ShoppingCart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_add_products_to_a_session_cart(): void
    {
        $product = Product::factory()->priced(1999)->withStock(10)->create(['name' => 'Running Shoes']);

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertRedirect();

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('store/Cart')
                ->where('cart.count', 2)
                ->where('cart.lines.0.product.name', 'Running Shoes')
                ->where('cart.lines.0.quantity', 2)
                ->where('cart.total.cents', 3998)
                ->where('cartCount', 2));
    }

    public function test_quantities_are_capped_at_available_stock(): void
    {
        $product = Product::factory()->withStock(3)->create(['name' => 'Yoga Mat']);

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $this->get(route('cart.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cart.lines.0.quantity', 3)
                ->where('cart.count', 3));
    }

    public function test_out_of_stock_products_are_not_added(): void
    {
        $product = Product::factory()->outOfStock()->create();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
        ]);

        $this->get(route('cart.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cart.lines', [])
                ->where('cart.count', 0));
    }

    public function test_cart_quantities_can_be_updated_and_removed(): void
    {
        $product = Product::factory()->withStock(8)->create();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->patch(route('cart.items.update', $product), ['quantity' => 1])
            ->assertRedirect();

        $this->get(route('cart.index'))
            ->assertInertia(fn (Assert $page) => $page->where('cart.lines.0.quantity', 1));

        $this->delete(route('cart.items.destroy', $product))->assertRedirect();

        $this->get(route('cart.index'))
            ->assertInertia(fn (Assert $page) => $page->where('cart.lines', []));
    }

    public function test_unavailable_items_are_removed_when_the_cart_is_viewed(): void
    {
        $product = Product::factory()->withStock(4)->create(['name' => 'Camping Tent']);

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $product->update(['stock' => 0]);

        $this->get(route('cart.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cart.lines', [])
                ->where('cart.notices.0', 'Camping Tent is out of stock and was removed from your cart.'));
    }

    public function test_a_guest_cart_is_merged_into_the_user_cart_on_login(): void
    {
        $product = Product::factory()->withStock(10)->create();
        $user = User::factory()->create();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticated();
        $this->assertSame(2, (int) $user->cart?->items()->where('product_id', $product->id)->value('quantity'));
        $this->assertSame([], session(ShoppingCart::SESSION_KEY, []));
    }

    public function test_merged_quantities_are_added_together_and_capped_at_stock(): void
    {
        $product = Product::factory()->withStock(5)->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
        $this->post(route('logout'));

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 4,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertSame(5, (int) $user->fresh()->cart?->items()->where('product_id', $product->id)->value('quantity'));
    }

    public function test_customers_persist_the_cart_in_the_database(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(6)->create();

        $this->actingAs($user)->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->assertSame([], session(ShoppingCart::SESSION_KEY, []));
    }
}
