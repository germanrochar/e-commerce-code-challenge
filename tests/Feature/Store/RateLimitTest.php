<?php

namespace Tests\Feature\Store;

use App\Models\Product;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_search_is_rate_limited(): void
    {
        RateLimiter::for('catalog', fn (Request $request) => Limit::perMinute(1)->by($request->ip()));
        Product::factory()->create();

        $this->get(route('home'))->assertOk();
        $this->get(route('home'))->assertTooManyRequests();
    }

    public function test_cart_mutations_are_rate_limited(): void
    {
        RateLimiter::for('cart', fn (Request $request) => Limit::perMinute(1)->by($request->ip()));
        $product = Product::factory()->create();

        $this->post(route('cart.items.store'), ['product_id' => $product->id])->assertRedirect();
        $this->post(route('cart.items.store'), ['product_id' => $product->id])->assertTooManyRequests();
    }

    public function test_checkout_is_rate_limited_more_tightly(): void
    {
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(1)->by((string) $request->user()?->id));
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post(route('cart.items.store'), ['product_id' => $product->id]);

        $this->actingAs($user)->get(route('checkout.show'))->assertOk();
        $this->actingAs($user)->get(route('checkout.show'))->assertTooManyRequests();
    }
}
