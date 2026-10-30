<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperAdminProvisioner
{
    public function create(string $email, string $password, ?string $name = null): User
    {
        if (! StrongPassword::accepts($password)) {
            throw new RuntimeException('Refused. The root password does not meet the strength rule. No account was created.');
        }

        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Refused. The administrator email is not valid.');
        }

        return $this->store($email, $password, $this->adminName($name), false);
    }

    public function createFromHash(string $email, #[\SensitiveParameter] string $passwordHash, ?string $name = null): User
    {
        $passwordHash = trim($passwordHash);
        if ($passwordHash === '' || ! Hash::isHashed($passwordHash) || ! Hash::verifyConfiguration($passwordHash)) {
            throw new RuntimeException('Refused. The root password hash is not valid for this application. No account was created.');
        }

        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Refused. The administrator email is not valid.');
        }

        return $this->store($email, $passwordHash, $this->adminName($name), true);
    }

    private function adminName(?string $name): string
    {
        $name = trim((string) ($name ?: getenv('ADMIN_NAME') ?: 'Delta Support Admin'));

        return $name === '' ? 'Delta Support Admin' : $name;
    }

    private function store(string $email, #[\SensitiveParameter] string $password, string $name, bool $verified): User
    {
        $lock = Cache::lock('root-admin-provision', 20);
        if (! $lock->get()) {
            throw new RuntimeException('Refused. Another admin setup is already running. No password was changed.');
        }

        try {
            return DB::transaction(function () use ($email, $password, $name, $verified): User {
                if (User::query()->where('is_admin', true)->lockForUpdate()->exists()) {
                    throw new RuntimeException('Refused. A Super Admin already exists. No password was changed.');
                }

                if (User::query()->where('email', $email)->lockForUpdate()->exists()) {
                    throw new RuntimeException('Refused. That email already exists. No password was changed.');
                }

                $attributes = [
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'is_admin' => true,
                    'must_change_password' => true,
                ];
                if ($verified) {
                    $attributes['email_verified_at'] = now();
                }

                $admin = User::query()->create($attributes);

                AdminActivityLog::query()->create([
                    'user_id' => $admin->id,
                    'action' => 'admin_provisioned',
                    'subject' => 'ST666666',
                    'format' => null,
                    'scope' => 'admin',
                    'filters' => ['email' => $email],
                    'row_count' => 1,
                ]);

                return $admin;
            });
        } finally {
            $lock->release();
        }
    }
}
