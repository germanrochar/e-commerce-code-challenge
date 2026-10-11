<?php

namespace App\Checkout;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class StockReservation
{
    public const int HOLD_MINUTES = 15;

    /**
     * @param  array<int, int>  $quantities
     */
    public function reserve(array $quantities): void
    {
        ksort($quantities);

        foreach ($quantities as $productId => $quantity) {
            $reserved = Product::query()
                ->whereKey($productId)
                ->where('stock', '>=', $quantity)
                ->decrement('stock', $quantity);

            if ($reserved === 0) {
                throw new InsufficientStock($productId);
            }
        }
    }

    public function release(Order $order, OrderStatus $status, array $attributes = []): bool
    {
        return DB::transaction(function () use ($order, $status, $attributes): bool {
            $claimed = Order::query()
                ->whereKey($order->id)
                ->where('status', OrderStatus::Pending)
                ->update([...$attributes, 'status' => $status]);

            if ($claimed === 0) {
                return false;
            }

            $items = OrderItem::query()
                ->where('order_id', $order->id)
                ->whereNotNull('product_id')
                ->orderBy('product_id')
                ->get(['product_id', 'quantity']);

            foreach ($items as $item) {
                Product::withTrashed()->whereKey($item->product_id)->increment('stock', $item->quantity);
            }

            return true;
        });
    }
}
