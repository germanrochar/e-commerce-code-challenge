<?php

namespace App\Http\Requests;

use App\Payments\Card;
use App\Rules\CardExpiry;
use App\Rules\CardNumber;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'card_number' => ['required', 'string', 'max:30', new CardNumber],
            'expiry' => ['required', 'string', 'max:10', new CardExpiry],
            'cvc' => ['required', 'string', 'regex:/^\d{3,4}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'cvc.regex' => 'The CVC must be 3 or 4 digits.',
        ];
    }

    public function card(): Card
    {
        return Card::fromInput(
            (string) $this->validated('card_number'),
            (string) $this->validated('expiry'),
            (string) $this->validated('cvc'),
        );
    }

    public function idempotencyKey(): string
    {
        return strtolower((string) $this->validated('idempotency_key'));
    }
}
