<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new LogicException('DatabaseSeeder is restricted to non-production environments.');
        }

        $this->seedUser(
            email: (string) env('SEED_ADMIN_EMAIL', 'admin@naslabs.my.id'),
            name: (string) env('SEED_ADMIN_NAME', 'Admin NasLabs'),
            password: (string) env('SEED_ADMIN_PASSWORD', 'password'),
            role: UserRole::Admin,
        );

        $this->seedUser(
            email: (string) env('SEED_USER_EMAIL', 'abel@naslabs.my.id'),
            name: (string) env('SEED_USER_NAME', 'Abel NasLabs'),
            password: (string) env('SEED_USER_PASSWORD', 'password'),
            role: UserRole::User,
        );
    }

    private function seedUser(string $email, string $name, string $password, UserRole $role): void
    {
        User::query()->updateOrCreate(
            ['email' => mb_strtolower(trim($email))],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => $role,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'quota_bytes' => (int) env('SEED_USER_QUOTA_BYTES', 26843545600),
                'used_bytes' => 0,
            ],
        );
    }
}
