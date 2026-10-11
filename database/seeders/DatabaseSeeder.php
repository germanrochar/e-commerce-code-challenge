<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->exists()) {
            $this->command->info('Database already seeded, skipping.');

            return;
        }

        $this->call(DemoUsersSeeder::class);
    }
}
