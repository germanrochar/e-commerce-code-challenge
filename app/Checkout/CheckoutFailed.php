<?php

namespace App\Checkout;

use RuntimeException;

class CheckoutFailed extends RuntimeException
{
    public static function emptyCart(): self
    {
        return new self('Your cart is empty.');
    }

    public static function cartChanged(): self
    {
        return new self('Some items in your cart changed. Please review your order before paying.');
    }

    public static function outOfStock(string $productName): self
    {
        return new self("Sorry, there isn't enough stock left of {$productName}. Your cart has been updated.");
    }

    public static function staleSession(): self
    {
        return new self('This checkout is no longer valid. Please try again.');
    }

    public static function inProgress(): self
    {
        return new self('Your previous payment is still being processed. Please wait a moment and check your orders.');
    }

    public static function paymentFailed(string $reason): self
    {
        return new self("{$reason} You have not been charged.");
    }

    public static function gatewayUnavailable(): self
    {
        return new self("We couldn't reach the payment provider. You have not been charged. Please try again.");
    }

    public static function reservationExpired(): self
    {
        return new self('Your reservation expired before the payment completed. You have not been charged. Please try again.');
    }
}
