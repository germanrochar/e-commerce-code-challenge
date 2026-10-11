<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $this->createUser('Store Admin', 'admin@example.com', UserRole::Admin);
        $this->createUser('Demo Customer', 'customer@example.com', UserRole::Customer);
    }

    private function createUser(string $name, string $email, UserRole $role): void
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->forceFill([
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
            ]);
        }

        $user->forceFill(['role' => $role])->save();
    }
}
