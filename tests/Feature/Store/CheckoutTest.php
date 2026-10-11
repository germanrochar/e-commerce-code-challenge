<?php

namespace Tests\Feature\Store;

use App\Enums\OrderStatus;
use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_asked_to_log_in_before_checkout(): void
    {
        $this->get(route('checkout.show'))->assertRedirect(route('login'));
        $this->post(route('checkout.store'), $this->card())->assertRedirect(route('login'));
    }

    public function test_an_empty_cart_is_sent_back_to_the_cart_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('checkout.show'))
            ->assertRedirect(route('cart.index'));
    }

    public function test_customers_can_open_checkout_with_test_cards(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->priced(2500)->withStock(4)->create();

        $this->actingAs($user)->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('checkout.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('store/Checkout')
                ->where('cart.count', 2)
                ->where('cart.total.cents', 5000)
                ->has('idempotencyKey')
                ->has('testCards', 4));
    }

    public function test_an_approved_card_places_the_order_and_queues_a_confirmation_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->priced(1999)->withStock(5)->create(['name' => 'Running Shoes']);

        $this->actingAs($user)->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $key = (string) Str::uuid();

        $this->actingAs($user)
            ->post(route('checkout.store'), $this->card(['idempotency_key' => $key]))
            ->assertRedirect();

        $order = Order::query()->sole();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(3998, $order->total_cents);
        $this->assertSame('Visa', $order->card_brand);
        $this->assertSame('4242', $order->card_last4);
        $this->assertSame($key, $order->idempotency_key);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(0, $user->cart?->items()->count() ?? 0);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame('Running Shoes', $order->items()->value('name'));

        Mail::assertQueued(OrderConfirmation::class, fn (OrderConfirmation $mail) => $mail->order->is($order));

        $this->actingAs($user)
            ->post(route('checkout.store'), $this->card(['idempotency_key' => $key]))
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_a_declined_card_releases_stock_and_keeps_the_cart(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->priced(1000)->withStock(4)->create();

        $this->actingAs($user)->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)
            ->from(route('checkout.show'))
            ->post(route('checkout.store'), $this->card([
                'card_number' => FakePaymentGateway::DECLINED,
            ]))
            ->assertRedirect(route('checkout.show'))
            ->assertSessionHasErrors('checkout');

        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(1, (int) $user->cart?->items()->value('quantity'));
        $this->assertSame(OrderStatus::Failed, Order::query()->sole()->status);
        Mail::assertNothingQueued();
    }

    public function test_insufficient_funds_and_gateway_errors_do_not_charge_the_customer(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(2)->create();

        $this->actingAs($user)->post(route('cart.items.store'), [
            'product_id' => $product->id,
        ]);

        $this->actingAs($user)
            ->post(route('checkout.store'), $this->card([
                'card_number' => FakePaymentGateway::INSUFFICIENT_FUNDS,
            ]))
            ->assertSessionHasErrors('checkout');

        $this->assertSame(2, $product->fresh()->stock);

        $this->actingAs($user)
            ->post(route('checkout.store'), $this->card([
                'card_number' => FakePaymentGateway::GATEWAY_ERROR,
            ]))
            ->assertSessionHasErrors('checkout');

        $this->assertSame(2, $product->fresh()->stock);
        $this->assertSame(0, Order::query()->where('status', OrderStatus::Paid)->count());
    }

    public function test_invalid_cards_are_rejected_before_a_charge(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(2)->create();

        $this->actingAs($user)->post(route('cart.items.store'), [
            'product_id' => $product->id,
        ]);

        $this->actingAs($user)
            ->post(route('checkout.store'), $this->card([
                'card_number' => '4242424242424241',
                'expiry' => '01/20',
                'cvc' => '12',
            ]))
            ->assertSessionHasErrors(['card_number', 'expiry', 'cvc']);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_the_last_unit_cannot_be_sold_twice(): void
    {
        $product = Product::factory()->priced(1500)->withStock(1)->create();
        $buyer = User::factory()->create();
        $late = User::factory()->create();

        $this->actingAs($buyer)->post(route('cart.items.store'), [
            'product_id' => $product->id,
        ]);
        $this->actingAs($late)->post(route('cart.items.store'), [
            'product_id' => $product->id,
        ]);

        $this->actingAs($buyer)
            ->post(route('checkout.store'), $this->card())
            ->assertRedirect();

        $this->actingAs($late)
            ->post(route('checkout.store'), $this->card())
            ->assertRedirect(route('checkout.show'))
            ->assertSessionHasErrors('checkout');

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame(1, Order::query()->where('status', OrderStatus::Paid)->count());
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function card(array $overrides = []): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'card_number' => FakePaymentGateway::APPROVED,
            'expiry' => now()->addYear()->format('m/y'),
            'cvc' => '123',
            ...$overrides,
        ];
    }
}
