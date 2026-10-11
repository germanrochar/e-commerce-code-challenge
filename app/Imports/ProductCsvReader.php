<?php

namespace App\Imports;

use App\Validation\ProductInput;
use Generator;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;

final class ProductCsvReader
{
    public const int HEADER_ROW = 1;

    private function __construct(
        private readonly Reader $reader,
        private readonly array $columns,
    ) {}

    public static function open(string $path): self
    {
        try {
            $reader = Reader::from($path);
            $reader->skipInputBOM();
            $header = $reader->nth(0);
        } catch (CsvException) {
            throw InvalidCsvFile::unreadable();
        }

        $columns = [];

        foreach ($header as $index => $name) {
            $columns[self::normalizeHeader((string) $name)] ??= $index;
        }

        $missing = array_values(array_diff(ProductInput::FIELDS, array_keys($columns)));

        if ($missing !== []) {
            throw InvalidCsvFile::missingColumns($missing);
        }

        return new self($reader, array_intersect_key($columns, array_flip(ProductInput::FIELDS)));
    }

    public function count(): int
    {
        $count = 0;

        foreach ($this->rows() as $ignored) {
            $count++;
        }

        return $count;
    }

    public function rows(int $afterRow = self::HEADER_ROW, ?int $untilRow = null): Generator
    {
        foreach ($this->reader->getRecords() as $offset => $record) {
            $row = $offset + 1;

            if ($row <= $afterRow) {
                continue;
            }

            if ($untilRow !== null && $row > $untilRow) {
                return;
            }

            yield $row => $this->map($record);
        }
    }

    public static function isBlank(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function map(array $record): array
    {
        $row = [];

        foreach ($this->columns as $field => $index) {
            $row[$field] = isset($record[$index]) ? self::toUtf8((string) $record[$index]) : null;
        }

        return $row;
    }

    private static function normalizeHeader(string $name): string
    {
        return (string) preg_replace('/[\s-]+/', '_', strtolower(trim($name, " \t\n\r\0\x0B\u{FEFF}")));
    }

    private static function toUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
