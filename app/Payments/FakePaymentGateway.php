<?php

namespace App\Payments;

use App\Support\Money;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

class FakePaymentGateway implements PaymentGateway
{
    public const string APPROVED = '4242424242424242';

    public const string DECLINED = '4000000000000002';

    public const string INSUFFICIENT_FUNDS = '4000000000009995';

    public const string GATEWAY_ERROR = '4000000000000119';

    public function __construct(private readonly int $latencyMs = 0) {}

    public static function testCards(): array
    {
        return [
            ['number' => self::APPROVED, 'outcome' => 'Approved'],
            ['number' => self::DECLINED, 'outcome' => 'Declined'],
            ['number' => self::INSUFFICIENT_FUNDS, 'outcome' => 'Insufficient funds'],
            ['number' => self::GATEWAY_ERROR, 'outcome' => 'Gateway error'],
        ];
    }

    public function charge(Money $amount, Card $card, string $idempotencyKey): PaymentResult
    {
        $this->simulateLatency();

        return match ($card->number) {
            self::DECLINED => PaymentResult::declined('Your card was declined.'),
            self::INSUFFICIENT_FUNDS => PaymentResult::declined('Your card has insufficient funds.'),
            self::GATEWAY_ERROR => throw new PaymentGatewayUnavailable('The payment provider could not be reached.'),
            default => PaymentResult::approved('fake_ch_'.Str::lower(Str::random(24))),
        };
    }

    public function refund(string $paymentReference): void
    {
        $this->simulateLatency();
    }

    private function simulateLatency(): void
    {
        if ($this->latencyMs > 0) {
            Sleep::for($this->latencyMs)->milliseconds();
        }
    }
}
