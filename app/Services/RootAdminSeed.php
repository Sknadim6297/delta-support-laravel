<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class RootAdminSeed
{
    public function __construct(
        private readonly RootAdminSeedFile $file,
        private readonly RootAdminBootstrap $bootstrap,
        private readonly SuperAdminProvisioner $provisioner,
        private readonly ProductionDatabaseTarget $targets,
    ) {}

    public function run(): void
    {
        if ($this->bootstrap->isConsumed()) {
            $this->file->delete();

            throw new RuntimeException('Refused. Root admin provisioning is already closed. No password was changed.');
        }

        if (User::query()->where('is_admin', true)->exists()) {
            $this->file->delete();

            throw new RuntimeException('Refused. A Super Admin already exists. No password was changed.');
        }

        try {
            $this->assertDatabase();
            $this->assertSchema();
            $input = $this->file->read();
            $this->provisioner->createFromHash($input['email'], $input['password_hash'], $input['name']);
            $this->bootstrap->consume();
        } catch (RuntimeException $exception) {
            $this->file->delete();

            throw $exception;
        } catch (Throwable $exception) {
            $this->file->delete();

            throw new RuntimeException('Refused. The Super Admin could not be created. No password was written to the log.');
        } finally {
            $this->file->delete();
        }
    }

    private function assertDatabase(): void
    {
        $connected = (string) DB::connection()->getDatabaseName();
        $approved = (string) config('deltasupport.admin_bootstrap_database');
        if ($connected === '' || $approved === '') {
            throw new RuntimeException('Refused. The connected database could not be verified. No account was created.');
        }

        if ($this->targets->isProtected($connected) || $this->targets->isProtected($approved)) {
            throw new RuntimeException('Refused. The connected database is protected. No account was created.');
        }

        if ($connected !== $approved) {
            throw new RuntimeException('Refused. The connected database is not '.$approved.'. No account was created.');
        }
    }

    private function assertSchema(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('admin_activity_logs')) {
            throw new RuntimeException('Refused. The admin tables are missing. No account was created.');
        }

        foreach (['name', 'email', 'password', 'email_verified_at', 'is_admin', 'must_change_password'] as $column) {
            if (! Schema::hasColumn('users', $column)) {
                throw new RuntimeException('Refused. The users table is missing the admin columns. No account was created.');
            }
        }
    }
}
