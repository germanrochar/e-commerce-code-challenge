<?php

namespace App\Console\Commands;

use App\Imports\ProductImporter;
use App\Jobs\ImportProducts;
use App\Models\ProductImport;
use App\Models\ProductImportError;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('products:import {file : Path to the CSV file} {--queue : Dispatch to the imports queue instead of running now} {--user= : Email of the admin to attribute the import to}')]
#[Description('Import products from a CSV file (name, sku, description, category, price, stock, weight_kg)')]
class ImportProductsCommand extends Command
{
    public function handle(ProductImporter $importer): int
    {
        $source = (string) $this->argument('file');

        if (! is_file($source) || ! is_readable($source)) {
            $this->error("File [{$source}] does not exist or is not readable.");

            return self::FAILURE;
        }

        $path = Storage::disk('local')->putFileAs('imports', new File($source), Str::uuid7().'.csv');

        if ($path === false) {
            $this->error('The file could not be copied to storage.');

            return self::FAILURE;
        }

        $user = $this->option('user') ? User::query()->where('email', $this->option('user'))->first() : null;
        $import = ProductImport::queue('local', $path, basename($source), $user);

        if ($this->option('queue')) {
            ImportProducts::dispatch($import);
            $this->info("Import #{$import->id} queued on the [".ImportProducts::QUEUE.'] queue.');

            return self::SUCCESS;
        }

        $started = microtime(true);
        $bar = null;

        $importer->run($import, function (ProductImport $import) use (&$bar): void {
            $bar ??= $this->output->createProgressBar($import->total_rows);
            $bar->setProgress($import->processed_rows);
        });

        $bar?->finish();
        $this->newLine(2);

        if ($import->failure_message !== null) {
            $this->error($import->failure_message);

            return self::FAILURE;
        }

        $this->table(['Rows', 'Created', 'Duplicates', 'Invalid', 'Empty', 'Seconds'], [[
            $import->total_rows,
            $import->created_count,
            $import->duplicate_count,
            $import->invalid_count,
            $import->skipped_empty_count,
            round(microtime(true) - $started, 2),
        ]]);

        $errors = ProductImportError::query()
            ->where('product_import_id', $import->getKey())
            ->orderBy('row_number')
            ->orderBy('id')
            ->limit(15)
            ->get(['row_number', 'column', 'message']);

        if ($errors->isNotEmpty()) {
            $this->table(['Row', 'Column', 'Problem'], $errors->map(fn (ProductImportError $error) => [$error->row_number, $error->column, $error->message])->all());

            if ($import->rejectedCount() > $errors->count()) {
                $this->line('Showing the first '.$errors->count().' problems. See all of them in the admin panel.');
            }
        }

        return self::SUCCESS;
    }
}
