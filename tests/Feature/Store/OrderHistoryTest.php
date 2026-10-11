<?php

namespace Tests\Feature\Store;

use App\Checkout\StockReservation;
use App\Enums\OrderStatus;
use App\Jobs\ReleaseExpiredReservations;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_orders(): void
    {
        $this->get(route('orders.index'))->assertRedirect(route('login'));
    }

    public function test_customers_see_only_their_own_orders(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = Order::factory()->for($user)->create();
        $theirs = Order::factory()->for($other)->create();

        $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('store/orders/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.number', $mine->number()));

        $this->actingAs($user)
            ->get(route('orders.show', $mine))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('store/orders/Show')
                ->where('order.number', $mine->number()));

        $this->actingAs($user)
            ->get(route('orders.show', $theirs))
            ->assertForbidden();
    }

    public function test_failed_orders_are_hidden_from_the_list(): void
    {
        $user = User::factory()->create();
        Order::factory()->for($user)->create();
        Order::factory()->for($user)->create(['status' => OrderStatus::Failed, 'paid_at' => null]);

        $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
    }

    public function test_abandoned_reservations_are_released_after_fifteen_minutes(): void
    {
        $product = Product::factory()->withStock(5)->create();
        $product->decrement('stock', 2);

        $order = Order::factory()
            ->pending()
            ->withItem($product, 2)
            ->create(['created_at' => now()->subMinutes(16)]);

        (new ReleaseExpiredReservations)->handle(app(StockReservation::class));

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);
    }
}
