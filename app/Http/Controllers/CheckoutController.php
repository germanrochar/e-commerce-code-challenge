<?php

namespace App\Http\Controllers;

use App\Cart\ShoppingCart;
use App\Checkout\CheckoutFailed;
use App\Checkout\PlaceOrder;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\CartResource;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function show(ShoppingCart $cart, PaymentGateway $gateway): Response|RedirectResponse
    {
        $contents = $cart->contents();

        if ($contents->isEmpty()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Your cart is empty.']);

            return to_route('cart.index');
        }

        return Inertia::render('store/Checkout', [
            'cart' => CartResource::of($contents),
            'idempotencyKey' => (string) Str::uuid(),
            'testCards' => $gateway instanceof FakePaymentGateway ? FakePaymentGateway::testCards() : [],
        ]);
    }

    public function store(CheckoutRequest $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        try {
            $order = $placeOrder->handle($user, $request->card(), $request->idempotencyKey());
        } catch (CheckoutFailed $exception) {
            return to_route('checkout.show')->withErrors(['checkout' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Thanks! Order #{$order->number()} has been placed."]);

        return to_route('orders.show', $order);
    }
}
