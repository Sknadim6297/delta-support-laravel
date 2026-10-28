<?php

namespace App\Console\Commands;

use App\Models\AdminActivityLog;
use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProvisionSuperAdmin extends Command
{
    protected $signature = 'admin:provision
        {--email= : Administrator email. Defaults to ADMIN_EMAIL}
        {--confirm= : Type provision-super-admin to create the account}';

    protected $description = 'Create the single Super Admin with a one-time password that must be changed at first login.';

    public function handle(): int
    {
        if ($this->option('confirm') !== 'provision-super-admin') {
            $this->error('Refused. Repeat with --confirm=provision-super-admin. No account was created.');

            return self::FAILURE;
        }

        if (DB::connection()->getDatabaseName() === 'delta_support') {
            $this->error('Refused. This command does not change the local delta_support database.');

            return self::FAILURE;
        }

        if (User::query()->where('is_admin', true)->exists()) {
            $this->error('Refused. A Super Admin already exists. No password was changed.');

            return self::FAILURE;
        }

        $email = trim((string) ($this->option('email') ?: env('ADMIN_EMAIL') ?: 'admin@deltasupport.local'));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Refused. The administrator email is not valid.');

            return self::FAILURE;
        }

        $password = StrongPassword::generate();
        if (! StrongPassword::accepts($password)) {
            throw new RuntimeException('The generated administrator password did not meet the strength rule.');
        }

        $admin = User::query()->create([
            'name' => (string) env('ADMIN_NAME', 'Delta Support Admin'),
            'email' => $email,
            'password' => $password,
            'is_admin' => true,
            'must_change_password' => true,
        ]);

        $path = storage_path('app/admin-one-time-password.txt');
        if (file_put_contents($path, $password) === false) {
            $admin->delete();
            $this->error('Refused. The one-time password file could not be written. No account was kept.');

            return self::FAILURE;
        }

        AdminActivityLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'admin_provisioned',
            'subject' => 'ST666666',
            'format' => null,
            'scope' => 'admin',
            'filters' => ['email' => $email],
            'row_count' => 1,
        ]);

        $this->info('Super Admin created. Login ID ST666666. The one-time password is in storage/app/admin-one-time-password.txt.');
        $this->line('Change it at first login, then delete that file. The password was not written to the application log.');

        return self::SUCCESS;
    }
}
