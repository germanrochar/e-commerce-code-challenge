<?php

namespace App\Payments;

use App\Support\Money;

interface PaymentGateway
{
    public function charge(Money $amount, Card $card, string $idempotencyKey): PaymentResult;

    public function refund(string $paymentReference): void;
}
