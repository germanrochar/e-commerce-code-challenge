<?php

namespace App\Imports;

use App\Enums\ImportErrorType;
use App\Enums\ImportStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductImportError;
use App\Support\Money;
use App\Validation\ProductInput;
use App\Validation\ProductRules;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\Validator;

final class ProductImporter
{
    public const int CHUNK_SIZE = 1000;

    private array $seenSkus = [];

    private array $categoryIds = [];

    public function __construct(private readonly ValidationFactory $validation) {}

    public function run(ProductImport $import, ?Closure $onChunk = null): ProductImport
    {
        if ($import->status->isFinished()) {
            return $import;
        }

        try {
            $reader = ProductCsvReader::open(Storage::disk($import->disk)->path($import->path));
        } catch (InvalidCsvFile $e) {
            $import->markFailed($e->getMessage());

            return $import;
        }

        $import->forceFill([
            'status' => ImportStatus::Processing,
            'started_at' => $import->started_at ?? now(),
            'total_rows' => $reader->count(),
        ])->save();

        $this->seenSkus = $this->rebuildSeenSkus($import, $reader);
        $this->categoryIds = Category::query()->pluck('id', 'slug')->all();

        $validator = $this->validation->make([], ProductRules::attributes(), ProductRules::messages());
        $chunk = [];

        foreach ($reader->rows(max($import->last_processed_row, ProductCsvReader::HEADER_ROW)) as $row => $raw) {
            $chunk[$row] = $raw;

            if (count($chunk) === self::CHUNK_SIZE) {
                $this->processChunk($import, $chunk, $validator, $onChunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $this->processChunk($import, $chunk, $validator, $onChunk);
        }

        $import->forceFill(['status' => ImportStatus::Completed, 'finished_at' => now()])->save();

        return $import;
    }

    private function processChunk(ProductImport $import, array $rows, Validator $validator, ?Closure $onChunk): void
    {
        $this->persistChunk($import, $rows, $validator);

        if ($onChunk !== null) {
            $onChunk($import);
        }
    }

    private function persistChunk(ProductImport $import, array $rows, Validator $validator): void
    {
        $errors = [];
        $valid = [];
        $invalidRows = [];
        $emptyCount = 0;

        foreach ($rows as $row => $raw) {
            if (ProductCsvReader::isBlank($raw)) {
                $emptyCount++;

                continue;
            }

            $data = ProductInput::normalize($raw);
            $validator->setData($data);

            if ($validator->fails()) {
                $invalidRows[$row] = true;

                foreach ($validator->errors()->messages() as $column => $messages) {
                    $errors[] = $this->error($import, $row, $data['sku'] ?? null, $column, ImportErrorType::Validation, $messages[0], $raw);
                }

                continue;
            }

            $valid[$row] = ['data' => $data, 'raw' => $raw];
        }

        $existingSkus = $this->existingSkus(array_map(fn (array $entry) => $entry['data']['sku'], $valid));

        $accepted = [];

        foreach ($valid as $row => ['data' => $data, 'raw' => $raw]) {
            $sku = $data['sku'];

            if (isset($this->seenSkus[$sku])) {
                $errors[] = $this->duplicate($import, $row, $sku, "Duplicate SKU '{$sku}': already used on row {$this->seenSkus[$sku]}.", $raw);

                continue;
            }

            if (isset($existingSkus[$sku])) {
                $errors[] = $this->duplicate($import, $row, $sku, "SKU '{$sku}' already exists.", $raw);

                continue;
            }

            $this->seenSkus[$sku] = $row;
            $accepted[$row] = ['data' => $data, 'raw' => $raw];
        }

        $this->resolveCategories(array_map(fn (array $entry) => $entry['data']['category'], $accepted));

        DB::transaction(function () use ($import, $rows, $accepted, &$errors, $invalidRows, $emptyCount): void {
            $created = $this->insertProducts($import, $accepted, $errors);

            foreach (array_chunk($errors, 500) as $batch) {
                ProductImportError::query()->insert($batch);
            }

            $import->forceFill([
                'processed_rows' => $import->processed_rows + count($rows),
                'last_processed_row' => (int) array_key_last($rows),
                'created_count' => $import->created_count + $created,
                'duplicate_count' => $import->duplicate_count + $this->countOfType($errors, ImportErrorType::Duplicate),
                'invalid_count' => $import->invalid_count + count($invalidRows),
                'skipped_empty_count' => $import->skipped_empty_count + $emptyCount,
            ])->save();
        });
    }

    private function insertProducts(ProductImport $import, array $accepted, array &$errors): int
    {
        if ($accepted === []) {
            return 0;
        }

        $now = now();
        $records = array_map(fn (array $entry) => $this->productRecord($entry['data'], $now), $accepted);

        try {
            DB::transaction(fn () => Product::query()->insert(array_values($records)));

            return count($records);
        } catch (UniqueConstraintViolationException) {
            $created = 0;

            foreach ($records as $row => $record) {
                try {
                    DB::transaction(fn () => Product::query()->insert($record));
                    $created++;
                } catch (UniqueConstraintViolationException) {
                    unset($this->seenSkus[$record['sku']]);
                    $errors[] = $this->duplicate($import, $row, $record['sku'], "SKU '{$record['sku']}' already exists.", $accepted[$row]['raw']);
                }
            }

            return $created;
        }
    }

    private function productRecord(array $data, mixed $now): array
    {
        return [
            'category_id' => $this->categoryIds[Category::slugFor($data['category'])],
            'sku' => $data['sku'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_cents' => Money::fromDecimal((string) $data['price'])->cents,
            'stock' => (int) $data['stock'],
            'weight_kg' => $data['weight_kg'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function existingSkus(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        return Product::withTrashed()
            ->whereIn('sku', array_values(array_unique($skus)))
            ->pluck('sku')
            ->mapWithKeys(fn (string $sku) => [strtoupper($sku) => true])
            ->all();
    }

    private function resolveCategories(array $names): void
    {
        $missing = [];

        foreach ($names as $name) {
            $slug = Category::slugFor($name);

            if (! isset($this->categoryIds[$slug])) {
                $missing[$slug] = Category::normalizeName($name);
            }
        }

        if ($missing === []) {
            return;
        }

        $now = now();

        Category::query()->insertOrIgnore(array_map(
            fn (string $slug, string $name) => ['name' => $name, 'slug' => $slug, 'created_at' => $now, 'updated_at' => $now],
            array_keys($missing),
            $missing,
        ));

        $this->categoryIds += Category::query()->whereIn('slug', array_keys($missing))->pluck('id', 'slug')->all();
    }

    private function rebuildSeenSkus(ProductImport $import, ProductCsvReader $reader): array
    {
        if ($import->last_processed_row <= ProductCsvReader::HEADER_ROW) {
            return [];
        }

        $rejectedRows = ProductImportError::query()
            ->where('product_import_id', $import->getKey())
            ->distinct()
            ->pluck('row_number')
            ->flip()
            ->all();
        $seen = [];

        foreach ($reader->rows(ProductCsvReader::HEADER_ROW, $import->last_processed_row) as $row => $raw) {
            if (isset($rejectedRows[$row]) || ProductCsvReader::isBlank($raw)) {
                continue;
            }

            $sku = ProductInput::normalize(['sku' => $raw['sku']])['sku'];
            $seen[$sku] ??= $row;
        }

        return $seen;
    }

    private function duplicate(ProductImport $import, int $row, string $sku, string $message, array $raw): array
    {
        return $this->error($import, $row, $sku, 'sku', ImportErrorType::Duplicate, $message, $raw);
    }

    private function error(ProductImport $import, int $row, mixed $sku, string $column, ImportErrorType $type, string $message, array $raw): array
    {
        return [
            'product_import_id' => $import->getKey(),
            'row_number' => $row,
            'sku' => is_string($sku) ? Str::limit($sku, 250, '') : null,
            'column' => $column,
            'type' => $type->value,
            'message' => Str::limit($message, 500),
            'raw' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ];
    }

    private function countOfType(array $errors, ImportErrorType $type): int
    {
        return count(array_filter($errors, fn (array $error) => $error['type'] === $type->value));
    }
}
