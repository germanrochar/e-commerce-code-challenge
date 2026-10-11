<?php

namespace App\Console\Commands;

use App\Validation\ProductInput;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Random\Engine\Mt19937;
use Random\Randomizer;
use SplFileObject;

#[Signature('products:generate-csv {rows=10000 : Number of data rows} {--path= : Output file (defaults to storage/app/generated-products.csv)} {--duplicates=2 : Percentage of rows reusing an earlier SKU} {--invalid=3 : Percentage of rows with invalid data} {--seed=42 : Seed for reproducible output}')]
#[Description('Generate a large products CSV for testing imports')]
class GenerateProductsCsvCommand extends Command
{
    private const array CATEGORIES = ['Electronics', 'Home & Office', 'Sports', 'Outdoors', 'Kitchen', 'Beauty', 'Books', 'Games', 'Accessories', 'Footwear', 'Clothing', 'Toys', 'Garden', 'Pets', 'Health'];

    private const array ADJECTIVES = ['Compact', 'Premium', 'Eco', 'Smart', 'Classic', 'Portable', 'Deluxe', 'Ultra', 'Vintage', 'Modern', 'Rugged', 'Wireless'];

    private const array NOUNS = ['Lamp', 'Speaker', 'Backpack', 'Bottle', 'Chair', 'Kettle', 'Charger', 'Notebook', 'Jacket', 'Blender', 'Tent', 'Watch', 'Mug', 'Headphones', 'Mat'];

    private const array INVALID_VARIANTS = [
        ['price', 'free'],
        ['stock', '-3'],
        ['name', ''],
        ['name', '<b>Bold</b> name'],
        ['category', ''],
        ['price', '12.999'],
    ];

    public function handle(): int
    {
        $rows = max(1, (int) $this->argument('rows'));
        $path = (string) ($this->option('path') ?: storage_path('app/generated-products.csv'));
        $duplicateRate = (float) $this->option('duplicates') / 100;
        $invalidRate = (float) $this->option('invalid') / 100;
        $random = new Randomizer(new Mt19937((int) $this->option('seed')));

        $file = new SplFileObject($path, 'w');
        $file->fputcsv(ProductInput::FIELDS, escape: '');

        $bar = $this->output->createProgressBar($rows);

        for ($i = 1; $i <= $rows; $i++) {
            $roll = $random->getFloat(0, 1);
            $sku = $i > 1 && $roll < $duplicateRate
                ? sprintf('GEN-%07d', $random->getInt(1, $i - 1))
                : sprintf('GEN-%07d', $i);

            $row = [
                'name' => $this->pick($random, self::ADJECTIVES).' '.$this->pick($random, self::NOUNS).' '.$i,
                'sku' => $sku,
                'description' => 'Generated product number '.$i.' for import load testing.',
                'category' => $this->pick($random, self::CATEGORIES),
                'price' => number_format($random->getInt(100, 99_999) / 100, 2, '.', ''),
                'stock' => (string) $random->getInt(0, 500),
                'weight_kg' => number_format($random->getInt(10, 20_000) / 1000, 3, '.', ''),
            ];

            if ($roll >= $duplicateRate && $roll < $duplicateRate + $invalidRate) {
                [$field, $value] = self::INVALID_VARIANTS[$i % count(self::INVALID_VARIANTS)];
                $row[$field] = $value;
            }

            $file->fputcsv(array_values($row), escape: '');

            if ($i % 1000 === 0) {
                $bar->advance(1000);
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Wrote {$rows} rows to {$path}");

        return self::SUCCESS;
    }

    private function pick(Randomizer $random, array $values): string
    {
        return $values[$random->getInt(0, count($values) - 1)];
    }
}
