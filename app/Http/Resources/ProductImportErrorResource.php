<?php

namespace App\Http\Resources;

use App\Models\ProductImportError;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImportErrorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $error = $this->resource;

        if (! $error instanceof ProductImportError) {
            return [];
        }

        return [
            'id' => $error->id,
            'row_number' => $error->row_number,
            'sku' => $error->sku,
            'column' => $error->column,
            'type' => $error->type->value,
            'message' => $error->message,
            'raw' => $error->raw,
        ];
    }
}
