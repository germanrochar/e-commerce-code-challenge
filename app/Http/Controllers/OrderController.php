<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Resources\OrderResource;
use App\Http\Resources\Paginated;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        $orders = $user->orders()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Pending])
            ->withSum('items', 'quantity')
            ->latest()
            ->latest('id')
            ->paginate(10);

        return Inertia::render('store/orders/Index', [
            'orders' => Paginated::of($orders, OrderResource::class),
        ]);
    }

    public function show(Order $order): Response
    {
        Gate::authorize('view', $order);

        $order->load('items.product');

        return Inertia::render('store/orders/Show', [
            'order' => OrderResource::make($order)->resolve(),
        ]);
    }
}
