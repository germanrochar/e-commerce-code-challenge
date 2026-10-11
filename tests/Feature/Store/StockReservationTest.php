<?php

namespace Tests\Feature\Store;

use App\Checkout\InsufficientStock;
use App\Checkout\StockReservation;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reserves_stock_with_a_conditional_update(): void
    {
        $first = Product::factory()->withStock(4)->create();
        $second = Product::factory()->withStock(2)->create();

        app(StockReservation::class)->reserve([
            $second->id => 1,
            $first->id => 3,
        ]);

        $this->assertSame(1, $first->fresh()->stock);
        $this->assertSame(1, $second->fresh()->stock);
    }

    public function test_it_does_not_oversell_when_stock_is_too_low(): void
    {
        $product = Product::factory()->withStock(1)->create();

        try {
            app(StockReservation::class)->reserve([$product->id => 2]);
            $this->fail('Expected insufficient stock.');
        } catch (InsufficientStock $exception) {
            $this->assertSame($product->id, $exception->productId);
        }

        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_it_releases_stock_for_a_pending_order_once(): void
    {
        $product = Product::factory()->withStock(5)->create();
        $product->decrement('stock', 2);
        $order = Order::factory()->pending()->withItem($product, 2)->create();
        $stock = app(StockReservation::class);

        $this->assertTrue($stock->release($order, OrderStatus::Failed));
        $this->assertFalse($stock->release($order->fresh(), OrderStatus::Expired));
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(OrderStatus::Failed, $order->fresh()->status);
    }
}
