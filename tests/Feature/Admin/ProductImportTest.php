<?php

namespace Tests\Feature\Admin;

use App\Enums\ImportStatus;
use App\Jobs\ImportProducts;
use App\Models\ProductImport;
use App\Models\User;
use Database\Seeders\SampleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function sampleUpload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'Code_Challenge_E-Commerce.csv',
            (string) file_get_contents(base_path(SampleCatalogSeeder::SAMPLE_FILE)),
        );
    }

    public function test_customers_cannot_import_products(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('admin.imports.index'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.imports.store'), ['file' => $this->sampleUpload()])->assertForbidden();

        $this->assertDatabaseCount('product_imports', 0);
    }

    public function test_uploading_a_file_queues_an_import_on_the_imports_queue(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.imports.store'), ['file' => $this->sampleUpload()]);

        $import = ProductImport::query()->sole();
        $response->assertRedirect(route('admin.imports.show', $import));
        $this->assertSame(ImportStatus::Queued, $import->status);
        $this->assertSame('Code_Challenge_E-Commerce.csv', $import->original_filename);
        $this->assertTrue($import->user->is($admin));
        Storage::disk('local')->assertExists($import->path);
        Queue::assertPushedOn(ImportProducts::QUEUE, ImportProducts::class, fn (ImportProducts $job) => $job->import->is($import));
    }

    public function test_the_import_page_shows_progress_and_paginated_problems(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.imports.store'), ['file' => $this->sampleUpload()]);
        $import = ProductImport::query()->sole();

        $this->get(route('admin.imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/imports/Show')
                ->where('import.status', 'completed')
                ->where('import.created_count', 85)
                ->where('import.rejected_count', 10)
                ->where('errors.meta.total', 10)
                ->where('errors.data.0.row_number', 7));

        $this->get(route('admin.imports.show', [$import, 'type' => 'duplicate']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('errors.meta.total', 3)
                ->where('filters.type', 'duplicate'));
    }

    public function test_rejected_rows_can_be_downloaded_as_csv(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.imports.store'), ['file' => $this->sampleUpload()]);
        $import = ProductImport::query()->sole();

        $response = $this->get(route('admin.imports.rejected', $import));

        $response->assertOk()->assertDownload('Code_Challenge_E-Commerce-rejected-rows.csv');
        $lines = array_values(array_filter(explode("\n", $response->streamedContent())));
        $this->assertCount(11, $lines);
        $this->assertSame('row_number,name,sku,description,category,price,stock,weight_kg,errors', $lines[0]);
        $this->assertStringStartsWith('7,"Yoga Mat",YM-015,', $lines[1]);
        $this->assertStringContainsString("Duplicate SKU 'RS-001': already used on row 2.", $response->streamedContent());
    }

    public function test_the_template_contains_the_expected_header(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.imports.template'));

        $response->assertOk()->assertDownload('products-template.csv');
        $this->assertStringStartsWith("name,sku,description,category,price,stock,weight_kg\n", $response->streamedContent());
    }

    public function test_only_csv_files_are_accepted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.imports.store'), ['file' => UploadedFile::fake()->image('photo.png')])
            ->assertSessionHasErrors('file');

        $this->actingAs($admin)
            ->post(route('admin.imports.store'), ['file' => UploadedFile::fake()->create('huge.csv', 60 * 1024, 'text/csv')])
            ->assertSessionHasErrors(['file' => 'The file may not be larger than 50 MB.']);

        $this->assertDatabaseCount('product_imports', 0);
    }

    public function test_a_job_that_keeps_failing_marks_the_import_as_failed(): void
    {
        $import = ProductImport::queue('local', 'imports/missing.csv', 'missing.csv');

        (new ImportProducts($import))->failed(null);

        $this->assertSame(ImportStatus::Failed, $import->refresh()->status);
        $this->assertNotNull($import->failure_message);
    }
}
