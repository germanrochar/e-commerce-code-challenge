<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        assert($order instanceof Order);

        return [
            'key' => $order->idempotency_key,
            'number' => $order->number(),
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'total' => $order->total(),
            'card' => $order->card_brand !== null ? "{$order->card_brand} ending in {$order->card_last4}" : null,
            'placed_at' => $order->created_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
            'item_count' => $this->whenAggregated('items', 'quantity', 'sum', fn ($sum) => (int) $sum),
            'items' => $this->whenLoaded('items', fn () => $order->items->map(fn (OrderItem $item): array => [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unitPrice(),
                'line_total' => $item->lineTotal(),
                'product_sku' => $item->product instanceof Product && ! $item->product->trashed() ? $item->product->sku : null,
            ])->all()),
        ];
    }
}
