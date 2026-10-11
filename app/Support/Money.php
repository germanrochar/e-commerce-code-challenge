<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use NumberFormatter;

final readonly class Money implements JsonSerializable
{
    public const string DEFAULT_CURRENCY = 'USD';

    public function __construct(
        public int $cents,
        public string $currency = self::DEFAULT_CURRENCY,
    ) {}

    public static function fromCents(int $cents, string $currency = self::DEFAULT_CURRENCY): self
    {
        return new self($cents, $currency);
    }

    public static function fromDecimal(string $amount, string $currency = self::DEFAULT_CURRENCY): self
    {
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException("Invalid monetary amount [{$amount}].");
        }

        [$units, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return new self(((int) $units * 100) + (int) str_pad($fraction, 2, '0'), $currency);
    }

    public function multiply(int $quantity): self
    {
        return new self($this->cents * $quantity, $this->currency);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents, $this->currency);
    }

    public function toDecimal(): string
    {
        return number_format($this->cents / 100, 2, '.', '');
    }

    public function format(): string
    {
        $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);

        return (string) $formatter->formatCurrency($this->cents / 100, $this->currency);
    }

    public function jsonSerialize(): array
    {
        return [
            'cents' => $this->cents,
            'amount' => $this->toDecimal(),
            'currency' => $this->currency,
            'formatted' => $this->format(),
        ];
    }
}
