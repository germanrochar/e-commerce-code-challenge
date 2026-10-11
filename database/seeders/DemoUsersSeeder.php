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
        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'email_verified_at' => now(),
        ])->save();
    }
}
