<?php

namespace App\Http\Resources;

use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $import = $this->resource;

        if (! $import instanceof ProductImport) {
            return [];
        }

        return [
            'id' => $import->id,
            'original_filename' => $import->original_filename,
            'status' => $import->status->value,
            'status_label' => $import->status->label(),
            'is_finished' => $import->status->isFinished(),
            'progress' => $import->progressPercentage(),
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'created_count' => $import->created_count,
            'duplicate_count' => $import->duplicate_count,
            'invalid_count' => $import->invalid_count,
            'skipped_empty_count' => $import->skipped_empty_count,
            'rejected_count' => $import->rejectedCount(),
            'failure_message' => $import->failure_message,
            'uploaded_by' => $import->user instanceof User ? $import->user->name : null,
            'created_at' => $import->created_at?->toIso8601String(),
            'started_at' => $import->started_at?->toIso8601String(),
            'finished_at' => $import->finished_at?->toIso8601String(),
        ];
    }
}
