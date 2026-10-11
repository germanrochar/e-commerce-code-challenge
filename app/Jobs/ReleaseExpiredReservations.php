<?php

namespace App\Jobs;

use App\Checkout\StockReservation;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

class ReleaseExpiredReservations implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function handle(StockReservation $stock): void
    {
        Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('created_at', '<', now()->subMinutes(StockReservation::HOLD_MINUTES))
            ->chunkById(100, function (Collection $orders) use ($stock): void {
                foreach ($orders as $order) {
                    $stock->release($order, OrderStatus::Expired, [
                        'payment_failure_reason' => 'The reservation expired before the payment completed.',
                    ]);
                }
            });
    }
}
