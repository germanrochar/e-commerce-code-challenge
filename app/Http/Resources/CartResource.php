<?php

namespace App\Http\Resources;

use App\Cart\CartContents;
use App\Cart\CartLine;

final class CartResource
{
    public static function of(CartContents $contents): array
    {
        return [
            'lines' => array_map(fn (CartLine $line): array => [
                'product' => ProductResource::make($line->product)->resolve(),
                'quantity' => $line->quantity,
                'line_total' => $line->lineTotal(),
            ], $contents->lines),
            'count' => $contents->count(),
            'total' => $contents->total(),
            'notices' => $contents->notices,
        ];
    }
}
