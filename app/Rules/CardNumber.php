<?php

namespace App\Rules;

use App\Payments\Card;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CardNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Card::passesLuhn(Card::normalizeNumber($value))) {
            $fail('Enter a valid card number.');
        }
    }
}
