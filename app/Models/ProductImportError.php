<?php

namespace App\Models;

use App\Enums\ImportErrorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[WithoutTimestamps]
#[Fillable(['product_import_id', 'row_number', 'sku', 'column', 'type', 'message', 'raw'])]
class ProductImportError extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ImportErrorType::class,
            'row_number' => 'integer',
            'raw' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(ProductImport::class, 'product_import_id');
    }
}
