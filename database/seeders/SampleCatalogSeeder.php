<?php

namespace Database\Seeders;

use App\Imports\ProductImporter;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class SampleCatalogSeeder extends Seeder
{
    public const string SAMPLE_FILE = 'database/data/sample-products.csv';

    public function run(ProductImporter $importer): void
    {
        if (Product::withTrashed()->exists()) {
            $this->command->info('Catalog already has products, skipping the sample import.');

            return;
        }

        $path = 'imports/sample-products.csv';
        Storage::disk('local')->put($path, (string) file_get_contents(base_path(self::SAMPLE_FILE)));

        $admin = User::query()->where('email', DemoUsersSeeder::ADMIN_EMAIL)->first();
        $import = $importer->run(ProductImport::queue('local', $path, 'sample-products.csv', $admin));

        $this->command->info("Imported {$import->created_count} sample products ({$import->rejectedCount()} rows rejected, see Admin > Imports).");
    }
}
