<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'original_filename',
    'disk',
    'path',
    'status',
    'total_rows',
    'processed_rows',
    'last_processed_row',
    'created_count',
    'duplicate_count',
    'invalid_count',
    'skipped_empty_count',
    'failure_message',
    'started_at',
    'finished_at',
])]
class ProductImport extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'last_processed_row' => 'integer',
            'created_count' => 'integer',
            'duplicate_count' => 'integer',
            'invalid_count' => 'integer',
            'skipped_empty_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ProductImportError::class);
    }

    public function progressPercentage(): int
    {
        if ($this->status === ImportStatus::Completed) {
            return 100;
        }

        if ($this->total_rows === 0) {
            return 0;
        }

        return (int) min(100, floor($this->processed_rows / $this->total_rows * 100));
    }
}
