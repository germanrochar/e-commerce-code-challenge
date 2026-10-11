<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public static function decimalAmounts(): array
    {
        return [
            'whole number' => ['18', 1800],
            'one decimal' => ['29.9', 2990],
            'two decimals' => ['449.99', 44999],
            'cents only' => ['0.05', 5],
            'float trap' => ['0.29', 29],
        ];
    }

    #[DataProvider('decimalAmounts')]
    public function test_it_converts_decimal_strings_to_cents_without_floating_point_errors(string $amount, int $expectedCents): void
    {
        $this->assertSame($expectedCents, Money::fromDecimal($amount)->cents);
    }

    public function test_it_rejects_amounts_with_more_than_two_decimals(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('1.999');
    }

    public function test_it_formats_as_currency(): void
    {
        $this->assertSame('$1,234.50', Money::fromCents(123450)->format());
    }

    public function test_arithmetic_returns_new_instances(): void
    {
        $unit = Money::fromCents(1999);

        $this->assertSame(5997, $unit->multiply(3)->cents);
        $this->assertSame(2000, $unit->add(Money::fromCents(1))->cents);
        $this->assertSame(1999, $unit->cents);
    }
}
