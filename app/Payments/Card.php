<?php

namespace App\Payments;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class Card
{
    public function __construct(
        #[\SensitiveParameter] public string $number,
        public int $expiryMonth,
        public int $expiryYear,
        #[\SensitiveParameter] public string $cvc,
    ) {}

    public static function fromInput(
        #[\SensitiveParameter] string $number,
        string $expiry,
        #[\SensitiveParameter] string $cvc,
    ): self {
        $parsed = self::parseExpiry($expiry);

        if ($parsed === null) {
            throw new InvalidArgumentException('Invalid card expiry.');
        }

        return new self(self::normalizeNumber($number), $parsed[0], $parsed[1], $cvc);
    }

    public static function normalizeNumber(#[\SensitiveParameter] string $number): string
    {
        return (string) preg_replace('/[\s-]+/', '', $number);
    }

    public static function passesLuhn(#[\SensitiveParameter] string $digits): bool
    {
        if (! preg_match('/^\d{12,19}$/', $digits)) {
            return false;
        }

        $sum = 0;

        foreach (array_reverse(str_split($digits)) as $position => $digit) {
            $value = (int) $digit;

            if ($position % 2 === 1) {
                $value *= 2;
                $value = $value > 9 ? $value - 9 : $value;
            }

            $sum += $value;
        }

        return $sum % 10 === 0;
    }

    public static function parseExpiry(string $expiry): ?array
    {
        if (! preg_match('/^\s*(\d{1,2})\s*\/?\s*(\d{2}|\d{4})\s*$/', $expiry, $matches)) {
            return null;
        }

        $month = (int) $matches[1];
        $year = (int) $matches[2];

        if ($month < 1 || $month > 12) {
            return null;
        }

        return [$month, $year < 100 ? 2000 + $year : $year];
    }

    public static function expiryHasPassed(int $month, int $year, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return $year < $now->year || ($year === $now->year && $month < $now->month);
    }

    public function brand(): string
    {
        return match (true) {
            str_starts_with($this->number, '4') => 'Visa',
            (bool) preg_match('/^(5[1-5]|222[1-9]|22[3-9]\d|2[3-6]\d{2}|27[01]\d|2720)/', $this->number) => 'Mastercard',
            (bool) preg_match('/^3[47]/', $this->number) => 'Amex',
            (bool) preg_match('/^(6011|65)/', $this->number) => 'Discover',
            default => 'Card',
        };
    }

    public function last4(): string
    {
        return substr($this->number, -4);
    }
}
