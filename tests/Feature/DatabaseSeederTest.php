<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_it_creates_the_demo_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::query()->where('email', 'admin@example.com')->sole()->isAdmin());
        $this->assertSame(UserRole::Customer, User::query()->where('email', 'customer@example.com')->sole()->role);
    }

    public function test_it_is_idempotent_and_repairs_demo_roles(): void
    {
        $this->seed(DatabaseSeeder::class);
        User::query()->where('email', 'admin@example.com')->update(['role' => UserRole::Customer->value, 'name' => 'Renamed']);

        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->sole();
        $this->assertTrue($admin->isAdmin());
        $this->assertSame('Renamed', $admin->name);
        $this->assertSame(2, User::query()->count());
        $this->assertSame(85, Product::query()->count());
        $this->assertSame(1, ProductImport::query()->count());
    }

    public function test_it_seeds_the_sample_catalog_attributed_to_the_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $import = ProductImport::query()->sole();
        $this->assertSame(85, $import->created_count);
        $this->assertSame('admin@example.com', $import->user?->email);
    }
}
