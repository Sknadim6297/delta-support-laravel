<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (app()->environment('production')) {
            throw new RuntimeException('Refused. Production admin accounts are created with admin:provision. No password was changed.');
        }

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD in the environment before seeding the admin account.');
        }

        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            if ($existing->is_admin !== true) {
                throw new RuntimeException('Refused. That email already belongs to a non-admin user. No password was changed.');
            }

            return;
        }

        User::query()->create([
            'name' => env('ADMIN_NAME', 'Delta Support Admin'),
            'email' => $email,
            'password' => $password,
            'is_admin' => true,
        ]);
    }
}
