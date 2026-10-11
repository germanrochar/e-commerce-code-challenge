<?php

namespace App\Rules;

use App\Payments\Card;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CardExpiry implements ValidationRule
{
    public const int MAX_YEARS_AHEAD = 20;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parsed = is_string($value) ? Card::parseExpiry($value) : null;

        if ($parsed === null) {
            $fail('Enter the expiry date as MM/YY.');

            return;
        }

        [$month, $year] = $parsed;

        if (Card::expiryHasPassed($month, $year)) {
            $fail('This card has expired.');
        } elseif ($year > now()->year + self::MAX_YEARS_AHEAD) {
            $fail('Enter a valid expiry date.');
        }
    }
}
