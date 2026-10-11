<?php

namespace Tests\Unit;

use App\Payments\Card;
use App\Payments\FakePaymentGateway;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CardTest extends TestCase
{
    public static function luhnNumbers(): array
    {
        return [
            'approved visa' => [FakePaymentGateway::APPROVED, true],
            'declined' => [FakePaymentGateway::DECLINED, true],
            'spaced' => ['4242 4242 4242 4242', true],
            'bad check digit' => ['4242424242424241', false],
            'too short' => ['42424242424', false],
            'letters' => ['4242abcd42424242', false],
        ];
    }

    #[DataProvider('luhnNumbers')]
    public function test_it_validates_card_numbers_with_luhn(string $number, bool $expected): void
    {
        $this->assertSame($expected, Card::passesLuhn(Card::normalizeNumber($number)));
    }

    public function test_it_detects_the_card_brand_and_last_four(): void
    {
        $card = Card::fromInput(FakePaymentGateway::APPROVED, '12/34', '123');

        $this->assertSame('Visa', $card->brand());
        $this->assertSame('4242', $card->last4());
    }

    public function test_it_parses_expiry_dates(): void
    {
        $this->assertSame([12, 2034], Card::parseExpiry('12/34'));
        $this->assertSame([3, 2030], Card::parseExpiry('3 / 30'));
        $this->assertNull(Card::parseExpiry('13/34'));
    }
}
