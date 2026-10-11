<?php

namespace App\Checkout;

use App\Cart\CartContents;
use App\Cart\CartLine;
use App\Cart\ShoppingCart;
use App\Enums\OrderStatus;
use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Payments\Card;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGatewayUnavailable;
use App\Payments\PaymentResult;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PlaceOrder
{
    public const int LOCK_SECONDS = 30;

    public const int LOCK_WAIT_SECONDS = 10;

    public function __construct(
        private readonly ShoppingCart $cart,
        private readonly StockReservation $stock,
        private readonly PaymentGateway $gateway,
    ) {}

    public function handle(User $user, Card $card, string $idempotencyKey): Order
    {
        try {
            return Cache::lock("checkout:user:{$user->id}", self::LOCK_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, fn (): Order => $this->place($user, $card, $idempotencyKey));
        } catch (LockTimeoutException) {
            throw CheckoutFailed::inProgress();
        }
    }

    private function place(User $user, Card $card, string $idempotencyKey): Order
    {
        $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing instanceof Order) {
            return $this->replay($existing, $user);
        }

        $contents = $this->cart->for($user)->contents();

        if ($contents->notices !== []) {
            throw CheckoutFailed::cartChanged();
        }

        if ($contents->isEmpty()) {
            throw CheckoutFailed::emptyCart();
        }

        $order = $this->reserve($user, $contents, $idempotencyKey);

        return $this->charge($order, $user, $card);
    }

    private function replay(Order $order, User $user): Order
    {
        if ($order->user_id !== $user->id) {
            throw CheckoutFailed::staleSession();
        }

        return match ($order->status) {
            OrderStatus::Paid, OrderStatus::Pending => $order,
            OrderStatus::Expired => throw CheckoutFailed::reservationExpired(),
            OrderStatus::Failed => throw CheckoutFailed::paymentFailed($order->payment_failure_reason ?? 'Your payment failed.'),
        };
    }

    private function reserve(User $user, CartContents $contents, string $idempotencyKey): Order
    {
        try {
            return DB::transaction(function () use ($user, $contents, $idempotencyKey): Order {
                $this->stock->reserve($contents->quantities());

                $order = $user->orders()->create([
                    'status' => OrderStatus::Pending,
                    'total_cents' => $contents->total()->cents,
                    'currency' => $contents->total()->currency,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $order->items()->createMany(array_map(fn (CartLine $line): array => [
                    'product_id' => $line->product->id,
                    'sku' => $line->product->sku,
                    'name' => $line->product->name,
                    'unit_price_cents' => $line->product->price_cents,
                    'quantity' => $line->quantity,
                    'line_total_cents' => $line->lineTotal()->cents,
                ], $contents->lines));

                return $order;
            });
        } catch (InsufficientStock $exception) {
            $name = Product::withTrashed()->whereKey($exception->productId)->value('name');

            throw CheckoutFailed::outOfStock(is_string($name) ? $name : 'an item');
        }
    }

    private function charge(Order $order, User $user, Card $card): Order
    {
        $paymentDetails = ['card_brand' => $card->brand(), 'card_last4' => $card->last4()];

        try {
            $result = $this->gateway->charge($order->total(), $card, $order->idempotency_key);
        } catch (PaymentGatewayUnavailable $exception) {
            $this->stock->release($order, OrderStatus::Failed, [
                ...$paymentDetails,
                'payment_failure_reason' => $exception->getMessage(),
            ]);

            throw CheckoutFailed::gatewayUnavailable();
        }

        if (! $result->approved) {
            $this->stock->release($order, OrderStatus::Failed, [
                ...$paymentDetails,
                'payment_failure_reason' => $result->failureReason,
            ]);

            throw CheckoutFailed::paymentFailed((string) $result->failureReason);
        }

        if (! $this->confirm($order, $user, $result, $paymentDetails)) {
            $this->gateway->refund((string) $result->reference);

            throw CheckoutFailed::reservationExpired();
        }

        $order->refresh();

        Mail::to($user)->queue(new OrderConfirmation($order));

        return $order;
    }

    private function confirm(Order $order, User $user, PaymentResult $result, array $paymentDetails): bool
    {
        return DB::transaction(function () use ($order, $user, $result, $paymentDetails): bool {
            $confirmed = Order::query()
                ->whereKey($order->id)
                ->where('status', OrderStatus::Pending)
                ->update([
                    ...$paymentDetails,
                    'status' => OrderStatus::Paid,
                    'payment_reference' => $result->reference,
                    'paid_at' => now(),
                ]);

            if ($confirmed === 0) {
                return false;
            }

            $this->cart->for($user)->clear();

            return true;
        });
    }
}
