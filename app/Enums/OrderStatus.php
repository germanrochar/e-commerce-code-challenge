<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function holdsStock(): bool
    {
        return $this === self::Pending || $this === self::Paid;
    }
}
