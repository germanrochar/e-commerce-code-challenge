<?php

namespace Tests\Feature\Imports;

use App\Enums\ImportErrorType;
use App\Enums\ImportStatus;
use App\Imports\ProductImporter;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use Database\Seeders\SampleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ProductImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function importContents(string $csv): ProductImport
    {
        return $this->runImport($this->queueImport($csv));
    }

    private function queueImport(string $csv): ProductImport
    {
        $path = 'imports/'.uniqid().'.csv';
        Storage::disk('local')->put($path, $csv);

        return ProductImport::queue('local', $path, 'products.csv');
    }

    private function runImport(ProductImport $import): ProductImport
    {
        return app(ProductImporter::class)->run($import)->refresh();
    }

    private function sampleCsv(): string
    {
        return (string) file_get_contents(base_path(SampleCatalogSeeder::SAMPLE_FILE));
    }

    private function errorsByRow(ProductImport $import): array
    {
        return $import->errors()->orderBy('row_number')->get()
            ->mapWithKeys(fn ($error) => [$error->row_number => $error->message])
            ->all();
    }

    public function test_it_imports_the_sample_file_with_exact_counts(): void
    {
        $import = $this->importContents($this->sampleCsv());

        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(97, $import->total_rows);
        $this->assertSame(97, $import->processed_rows);
        $this->assertSame(85, $import->created_count);
        $this->assertSame(3, $import->duplicate_count);
        $this->assertSame(7, $import->invalid_count);
        $this->assertSame(2, $import->skipped_empty_count);
        $this->assertSame(85, Product::query()->count());
        $this->assertSame(100, $import->progressPercentage());
    }

    public function test_it_reports_each_problem_on_the_right_row(): void
    {
        $import = $this->importContents($this->sampleCsv());

        $this->assertSame([
            7 => 'The price must be a number with at most 2 decimals.',
            16 => 'The stock cannot be negative.',
            20 => 'The name must not contain HTML.',
            25 => 'The name field is required.',
            36 => "Duplicate SKU 'RS-001': already used on row 2.",
            41 => 'The name field is required.',
            47 => 'The price must be greater than zero.',
            52 => 'The category field is required.',
            56 => "Duplicate SKU 'BS-021': already used on row 11.",
            89 => "Duplicate SKU 'BS-021': already used on row 11.",
        ], $this->errorsByRow($import));

        $duplicate = $import->errors()->where('row_number', 36)->sole();
        $this->assertSame(ImportErrorType::Duplicate, $duplicate->type);
        $this->assertSame('Updated lightweight shoes — now with better arch support', $duplicate->raw['description']);
    }

    public function test_the_first_valid_row_wins_and_values_are_normalised(): void
    {
        $this->importContents($this->sampleCsv());

        $shoes = Product::query()->where('sku', 'RS-001')->sole();
        $this->assertSame(8999, $shoes->price_cents);
        $this->assertSame(150, $shoes->stock);

        $this->assertSame(2999, Product::query()->where('sku', 'WM-042')->sole()->price_cents);
        $this->assertNull(Product::query()->where('sku', 'GK-088')->sole()->weight_kg);
        $this->assertSame(0, Product::query()->where('sku', 'VC-001')->sole()->stock);
        $this->assertSame('Comma, In Product Name', Product::query()->where('sku', 'CI-001')->sole()->name);
        $this->assertSame('Quote "Inside" Name', Product::query()->where('sku', 'QI-001')->sole()->name);
        $this->assertSame('Stainless steel, 1L — keeps drinks cold for 24hrs™', Product::query()->where('sku', 'WB-033')->sole()->description);
    }

    public function test_sql_looking_values_are_stored_as_plain_text(): void
    {
        $this->importContents($this->sampleCsv());

        $this->assertSame("Robert'); DROP TABLE products;--", Product::query()->where('sku', 'SQL-001')->sole()->name);
        $this->assertDatabaseMissing('products', ['sku' => 'XS-001']);
    }

    public function test_categories_are_created_once_and_reused(): void
    {
        Category::factory()->named('Footwear')->create();

        $this->importContents($this->sampleCsv());

        $this->assertSame(1, Category::query()->where('slug', 'footwear')->count());
        $this->assertSame(['Food & Beverage'], Category::query()->where('slug', 'food-beverage')->pluck('name')->all());
        $this->assertSame(3, Category::query()->where('slug', 'footwear')->sole()->products()->count());
    }

    public function test_reimporting_the_same_file_creates_nothing_and_reports_existing_skus(): void
    {
        $this->importContents($this->sampleCsv());

        $second = $this->importContents($this->sampleCsv());

        $this->assertSame(0, $second->created_count);
        $this->assertSame(88, $second->duplicate_count);
        $this->assertSame(7, $second->invalid_count);
        $this->assertSame(85, Product::query()->count());
        $this->assertSame("SKU 'RS-001' already exists.", $this->errorsByRow($second)[2]);
    }

    public function test_skus_of_deleted_products_are_reported_as_existing(): void
    {
        Product::factory()->create(['sku' => 'RS-001'])->delete();

        $import = $this->importContents($this->sampleCsv());

        $errors = $this->errorsByRow($import);
        $this->assertSame("SKU 'RS-001' already exists.", $errors[2]);
        $this->assertSame("SKU 'RS-001' already exists.", $errors[36]);
        $this->assertSame(84, $import->created_count);
    }

    public function test_sku_matching_is_case_and_whitespace_insensitive(): void
    {
        $import = $this->importContents(implode("\n", [
            'name,sku,description,category,price,stock,weight_kg',
            'Mug,mg-001,,Kitchen,5.00,1,',
            'Mug again, MG-001 ,,Kitchen,5.00,1,',
        ]));

        $this->assertSame([3 => "Duplicate SKU 'MG-001': already used on row 2."], $this->errorsByRow($import));
    }

    public function test_an_invalid_first_occurrence_does_not_block_a_later_valid_row(): void
    {
        $import = $this->importContents(implode("\n", [
            'name,sku,description,category,price,stock,weight_kg',
            'Mug,MG-001,,Kitchen,free,1,',
            'Mug,MG-001,,Kitchen,5.00,1,',
        ]));

        $this->assertSame(1, $import->created_count);
        $this->assertSame(500, Product::query()->where('sku', 'MG-001')->sole()->price_cents);
    }

    public function test_every_failing_column_is_reported_but_the_row_counts_once(): void
    {
        $import = $this->importContents(implode("\n", [
            'name,sku,description,category,price,stock,weight_kg',
            ',BAD SKU!,,,-1,abc,heavy',
        ]));

        $this->assertSame(1, $import->invalid_count);
        $this->assertEqualsCanonicalizing(
            ['name', 'sku', 'category', 'price', 'stock', 'weight_kg'],
            $import->errors()->pluck('column')->all(),
        );
    }

    public function test_headers_are_matched_flexibly_and_extra_columns_ignored(): void
    {
        $import = $this->importContents("\u{FEFF}SKU,Name,Price,Stock,Category,Weight KG,Description,Supplier\r\nAB-1,Lamp,10.50,3,Home,1.2,Bright,ACME\r\n");

        $this->assertSame(1, $import->created_count);
        $this->assertDatabaseHas('products', ['sku' => 'AB-1', 'name' => 'Lamp', 'price_cents' => 1050]);
    }

    public function test_windows_1252_files_are_converted_to_utf8(): void
    {
        $csv = "name,sku,description,category,price,stock,weight_kg\nCaf\xE9 Mug,CM-1,Cr\xE8me colour,Kitchen,5.00,1,\n";

        $this->importContents($csv);

        $this->assertSame('Café Mug', Product::query()->where('sku', 'CM-1')->sole()->name);
    }

    #[DataProvider('brokenFiles')]
    public function test_files_with_missing_columns_fail_with_a_clear_message(string $csv, string $message): void
    {
        $import = $this->importContents($csv);

        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertSame($message, $import->failure_message);
        $this->assertSame(0, Product::query()->count());
    }

    public static function brokenFiles(): array
    {
        return [
            'missing price and stock' => ["name,sku,description,category,weight_kg\nA,B,C,D,1\n", 'The file is missing the required column(s): price, stock.'],
            'empty file' => ['', 'The file is missing the required column(s): name, sku, description, category, price, stock, weight_kg.'],
        ];
    }

    public function test_it_resumes_after_a_crash_without_duplicating_work(): void
    {
        $lines = ['name,sku,description,category,price,stock,weight_kg'];

        for ($i = 1; $i <= 1500; $i++) {
            $lines[] = "Product {$i},P-{$i},,Misc,1.00,1,";
        }

        $lines[] = 'Product again,P-5,,Misc,1.00,1,';
        $import = $this->queueImport(implode("\n", $lines));

        $crashAfterFirstChunk = function (): void {
            throw new RuntimeException('Worker killed');
        };

        try {
            app(ProductImporter::class)->run($import, $crashAfterFirstChunk);
            $this->fail('The import should have crashed.');
        } catch (RuntimeException) {
        }

        $import->refresh();
        $this->assertSame(ImportStatus::Processing, $import->status);
        $this->assertSame(1001, $import->last_processed_row);
        $this->assertSame(1000, Product::query()->count());

        $import = $this->runImport($import);

        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(1501, $import->processed_rows);
        $this->assertSame(1500, $import->created_count);
        $this->assertSame(1500, Product::query()->count());
        $this->assertSame([1502 => "Duplicate SKU 'P-5': already used on row 6."], $this->errorsByRow($import));
    }

    public function test_finished_imports_are_not_processed_again(): void
    {
        $import = $this->importContents($this->sampleCsv());

        $this->runImport($import);

        $this->assertSame(85, Product::query()->count());
        $this->assertSame(10, $import->errors()->count());
    }

    public function test_it_imports_twenty_thousand_rows_in_batches(): void
    {
        $path = Storage::disk('local')->path('imports/large.csv');
        @mkdir(dirname($path), recursive: true);

        $this->artisan('products:generate-csv', ['rows' => 20_000, '--path' => $path])->assertSuccessful();

        $import = ProductImport::queue('local', 'imports/large.csv', 'large.csv');

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $memoryBefore = memory_get_usage(true);
        $import = $this->runImport($import);
        $memoryGrowth = memory_get_usage(true) - $memoryBefore;

        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(20_000, $import->total_rows);
        $this->assertSame(20_000, $import->created_count + $import->duplicate_count + $import->invalid_count + $import->skipped_empty_count);
        $this->assertGreaterThan(19_000, $import->created_count);
        $this->assertGreaterThan(0, $import->duplicate_count);
        $this->assertGreaterThan(0, $import->invalid_count);
        $this->assertSame($import->created_count, Product::query()->count());
        $this->assertLessThan(200, $queries, "Expected batched queries, got {$queries}.");
        $this->assertLessThan(64 * 1024 * 1024, $memoryGrowth);
    }
}
