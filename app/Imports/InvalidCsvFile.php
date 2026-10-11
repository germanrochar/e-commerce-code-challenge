<?php

namespace App\Imports;

use RuntimeException;

final class InvalidCsvFile extends RuntimeException
{
    public static function unreadable(): self
    {
        return new self('The file could not be read as CSV.');
    }

    public static function missingColumns(array $missing): self
    {
        return new self('The file is missing the required column(s): '.implode(', ', $missing).'.');
    }
}
