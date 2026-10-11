<?php

namespace App\Http\Controllers;

use App\Cart\ShoppingCart;
use App\Http\Resources\CartResource;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(ShoppingCart $cart): Response
    {
        return Inertia::render('store/Cart', [
            'cart' => CartResource::of($cart->contents()),
        ]);
    }

    public function store(Request $request, ShoppingCart $cart): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.ShoppingCart::MAX_QUANTITY],
        ]);

        $product = Product::query()->whereKey($validated['product_id'])->firstOrFail();
        $requested = (int) ($validated['quantity'] ?? 1);
        $before = $cart->quantityOf($product);
        $after = $cart->add($product, $requested);

        Inertia::flash('toast', match (true) {
            $after === 0 => ['type' => 'warning', 'message' => "{$product->name} is out of stock."],
            $after === $before => ['type' => 'warning', 'message' => "You already have all {$after} available {$product->name} in your cart."],
            $after < $before + $requested => ['type' => 'info', 'message' => "Only {$after} of {$product->name} available. Your cart has been updated."],
            default => ['type' => 'success', 'message' => "{$product->name} added to your cart."],
        });

        return back();
    }

    public function update(Request $request, Product $product, ShoppingCart $cart): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:'.ShoppingCart::MAX_QUANTITY],
        ]);

        $requested = (int) $validated['quantity'];
        $quantity = $cart->set($product, $requested);

        if ($quantity < $requested) {
            Inertia::flash('toast', ['type' => 'info', 'message' => "Only {$quantity} of {$product->name} available."]);
        }

        return back();
    }

    public function destroy(Product $product, ShoppingCart $cart): RedirectResponse
    {
        $cart->remove($product);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$product->name} removed from your cart."]);

        return back();
    }
}
