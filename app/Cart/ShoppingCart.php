<?php

namespace App\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

class ShoppingCart
{
    public const string SESSION_KEY = 'cart.items';

    public const int MAX_QUANTITY = 99;

    public function __construct(
        private readonly Session $session,
        private readonly ?User $user = null,
    ) {}

    public function for(?User $user): self
    {
        return new self($this->session, $user);
    }

    /**
     * @return array<int, int>
     */
    public function quantities(): array
    {
        if ($this->user === null) {
            return $this->guestQuantities();
        }

        return CartItem::query()
            ->whereHas('cart', fn ($query) => $query->where('user_id', $this->user->id))
            ->orderBy('id')
            ->pluck('quantity', 'product_id')
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->all();
    }

    public function count(): int
    {
        return array_sum($this->quantities());
    }

    public function quantityOf(Product $product): int
    {
        return $this->quantities()[$product->id] ?? 0;
    }

    public function add(Product $product, int $quantity): int
    {
        return $this->set($product, $this->quantityOf($product) + $quantity);
    }

    public function set(Product $product, int $quantity): int
    {
        $quantity = $this->cap($product, $quantity);

        $this->write($product->id, $quantity);

        return $quantity;
    }

    public function remove(Product $product): void
    {
        $this->write($product->id, 0);
    }

    public function clear(): void
    {
        if ($this->user === null) {
            $this->session->forget(self::SESSION_KEY);

            return;
        }

        CartItem::query()->whereHas('cart', fn ($query) => $query->where('user_id', $this->user->id))->delete();
    }

    public function contents(): CartContents
    {
        $quantities = $this->quantities();

        if ($quantities === []) {
            return new CartContents([]);
        }

        $products = Product::withTrashed()
            ->with('category:id,name,slug')
            ->whereKey(array_keys($quantities))
            ->get()
            ->keyBy('id');

        $lines = [];
        $notices = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);
            $available = $product instanceof Product && ! $product->trashed() ? $this->cap($product, $quantity) : 0;

            if ($available !== $quantity) {
                $this->write($productId, $available);
                $notices[] = $this->adjustmentNotice($product, $available);
            }

            if ($product instanceof Product && $available > 0) {
                $lines[] = new CartLine($product, $available);
            }
        }

        return new CartContents($lines, $notices);
    }

    public function mergeGuestCartInto(User $user): void
    {
        $guest = $this->guestQuantities();

        if ($guest === []) {
            return;
        }

        $this->session->forget(self::SESSION_KEY);

        $cart = $this->for($user);
        $existing = $cart->quantities();
        $products = Product::query()->whereKey(array_keys($guest))->get();

        foreach ($products as $product) {
            $cart->set($product, ($existing[$product->id] ?? 0) + $guest[$product->id]);
        }
    }

    private function cap(Product $product, int $quantity): int
    {
        return max(0, min($quantity, $product->stock, self::MAX_QUANTITY));
    }

    private function adjustmentNotice(?Product $product, int $available): string
    {
        if (! $product instanceof Product || $product->trashed()) {
            return 'An item in your cart is no longer available and was removed.';
        }

        return $available === 0
            ? "{$product->name} is out of stock and was removed from your cart."
            : "Only {$available} of {$product->name} available, so your quantity was updated.";
    }

    /**
     * @return array<int, int>
     */
    private function guestQuantities(): array
    {
        $stored = $this->session->get(self::SESSION_KEY, []);

        return is_array($stored) ? array_map('intval', $stored) : [];
    }

    private function write(int $productId, int $quantity): void
    {
        if ($this->user === null) {
            $items = $this->guestQuantities();

            if ($quantity > 0) {
                $items[$productId] = $quantity;
            } else {
                unset($items[$productId]);
            }

            $this->session->put(self::SESSION_KEY, $items);

            return;
        }

        $cart = Cart::query()->firstOrCreate(['user_id' => $this->user->id]);

        if ($quantity > 0) {
            $cart->items()->updateOrCreate(['product_id' => $productId], ['quantity' => $quantity]);
        } else {
            $cart->items()->where('product_id', $productId)->delete();
        }
    }
}
