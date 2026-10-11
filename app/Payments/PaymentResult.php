<?php

namespace App\Payments;

final readonly class PaymentResult
{
    private function __construct(
        public bool $approved,
        public ?string $reference,
        public ?string $failureReason,
    ) {}

    public static function approved(string $reference): self
    {
        return new self(true, $reference, null);
    }

    public static function declined(string $reason): self
    {
        return new self(false, null, $reason);
    }
}
