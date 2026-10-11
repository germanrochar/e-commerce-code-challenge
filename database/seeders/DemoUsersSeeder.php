<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $this->createUser('Store Admin', 'admin@example.com');
        $this->createUser('Demo Customer', 'customer@example.com');
    }

    private function createUser(string $name, string $email): void
    {
        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'email_verified_at' => now(),
        ])->save();
    }
}
