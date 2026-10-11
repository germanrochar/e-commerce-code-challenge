<?php

namespace App\Validation;

use Normalizer;

final class ProductInput
{
    public const array FIELDS = ['name', 'sku', 'description', 'category', 'price', 'stock', 'weight_kg'];

    private const string CURRENCY_PREFIX = '/^(?:US)?[$€£]\s*/u';

    public static function normalize(array $input): array
    {
        $normalized = [];

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];

            $normalized[$field] = match ($field) {
                'description' => self::text($value, collapseWhitespace: false),
                'sku' => self::sku($value),
                'price' => self::price($value),
                default => self::text($value),
            };
        }

        return $normalized;
    }

    public static function hasCurrencyPrefix(mixed $price): bool
    {
        return is_string($price) && preg_match(self::CURRENCY_PREFIX, trim($price)) === 1;
    }

    private static function text(mixed $value, bool $collapseWhitespace = true): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;

        if ($collapseWhitespace) {
            $value = (string) preg_replace('/\s+/u', ' ', $value);
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function sku(mixed $value): mixed
    {
        $value = self::text($value);

        return is_string($value) ? strtoupper($value) : $value;
    }

    private static function price(mixed $value): mixed
    {
        $value = self::text($value);

        return is_string($value) ? (string) preg_replace(self::CURRENCY_PREFIX, '', $value) : $value;
    }
}
