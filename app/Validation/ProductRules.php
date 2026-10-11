<?php

namespace App\Validation;

use App\Models\Product;
use App\Rules\NoHtml;
use Illuminate\Validation\Rule;

final class ProductRules
{
    public const int MAX_PRICE_CENTS = 99_999_999;

    public const int MAX_STOCK = 1_000_000;

    public const string SKU_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]*$/';

    public static function attributes(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new NoHtml],
            'sku' => ['required', 'string', 'max:64', 'regex:'.self::SKU_PATTERN],
            'description' => ['nullable', 'string', 'max:5000', new NoHtml],
            'category' => ['required', 'string', 'max:100', new NoHtml],
            'price' => ['required', 'decimal:0,2', 'regex:/^\d+(\.\d{1,2})?$/', 'gte:0.01', 'lte:'.(self::MAX_PRICE_CENTS / 100)],
            'stock' => ['required', 'integer', 'min:0', 'max:'.self::MAX_STOCK],
            'weight_kg' => ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:10000'],
        ];
    }

    public static function forForm(?Product $ignore = null): array
    {
        $rules = self::attributes();

        $rules['sku'][] = Rule::unique('products', 'sku')->ignore($ignore?->getKey());

        return $rules;
    }

    public static function messages(): array
    {
        return [
            'sku.regex' => 'The SKU may only contain letters, numbers, dots, dashes and underscores.',
            'sku.unique' => 'This SKU is already used by another product.',
            'price.decimal' => 'The price must be a number with at most 2 decimals.',
            'price.regex' => 'The price must be a number with at most 2 decimals.',
            'price.gte' => 'The price must be greater than zero.',
            'stock.min' => 'The stock cannot be negative.',
        ];
    }
}
