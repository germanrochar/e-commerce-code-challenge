<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    public const string ADMIN_EMAIL = 'admin@example.com';

    public const string CUSTOMER_EMAIL = 'customer@example.com';

    public function run(): void
    {
        $this->createUser('Store Admin', self::ADMIN_EMAIL, UserRole::Admin);
        $this->createUser('Demo Customer', self::CUSTOMER_EMAIL, UserRole::Customer);
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
