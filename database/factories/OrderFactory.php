<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::Paid,
            'total_cents' => 1999,
            'currency' => 'USD',
            'idempotency_key' => fake()->unique()->uuid(),
            'payment_reference' => 'fake_ch_'.Str::lower(Str::random(24)),
            'card_brand' => 'Visa',
            'card_last4' => '4242',
            'paid_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Pending,
            'payment_reference' => null,
            'card_brand' => null,
            'card_last4' => null,
            'paid_at' => null,
        ]);
    }

    public function withItem(Product $product, int $quantity = 1): static
    {
        return $this->afterCreating(function (Order $order) use ($product, $quantity): void {
            $order->items()->create([
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'unit_price_cents' => $product->price_cents,
                'quantity' => $quantity,
                'line_total_cents' => $product->price_cents * $quantity,
            ]);

            $order->update(['total_cents' => $product->price_cents * $quantity]);
        });
    }
}
