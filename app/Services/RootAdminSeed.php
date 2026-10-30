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

        $this->assertDatabase();
        $this->assertSchema();
        $input = $this->file->read();

        try {
            $this->provisioner->createFromHash($input['email'], $input['password_hash'], $input['name']);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException('Refused. The Super Admin could not be created. No password was written to the log.');
        }

        $this->file->delete();
        $this->bootstrap->consume();
    }

    public function approvedDatabaseName(): string
    {
        $configured = config('deltasupport.admin_bootstrap_database');
        if (! is_string($configured)) {
            return 'Delta_support_db';
        }

        return trim($configured);
    }

    public function identityRefusal(string $driver, string $configured, string $selected, string $approved): ?string
    {
        $approved = trim($approved);
        $configured = trim($configured);
        $selected = trim($selected);
        $mysql = in_array($driver, ['mysql', 'mariadb'], true);

        if ($approved === '' || $selected === '' || ($mysql && $configured === '')) {
            return 'Refused. The connected database could not be verified. No account was created.';
        }

        $same = function (string $left, string $right) use ($mysql): bool {
            return $mysql ? strtolower($left) === strtolower($right) : $left === $right;
        };

        if ($configured !== '' && ! $same($configured, $selected)) {
            return 'Refused. The configured database does not match the connected schema. No account was created.';
        }

        if ($this->targets->isProtected($selected) || $this->targets->isProtected($approved)) {
            return 'Refused. The connected database is protected. No account was created.';
        }

        if (! $same($selected, $approved)) {
            return 'Refused. The connected database is not '.$approved.'. No account was created.';
        }

        return null;
    }

    private function assertDatabase(): void
    {
        $connection = DB::connection();
        $driver = (string) $connection->getDriverName();
        $configured = trim((string) $connection->getConfig('database'));
        if ($configured === '') {
            $configured = trim((string) $connection->getDatabaseName());
        }

        $selected = in_array($driver, ['mysql', 'mariadb'], true)
            ? $this->selectedMysqlDatabase()
            : trim((string) $connection->getDatabaseName());

        $refusal = $this->identityRefusal($driver, $configured, $selected, $this->approvedDatabaseName());
        if ($refusal !== null) {
            throw new RuntimeException($refusal);
        }
    }

    private function selectedMysqlDatabase(): string
    {
        try {
            $row = DB::select('SELECT DATABASE() AS selected_database');
        } catch (Throwable) {
            return '';
        }

        $selected = $row[0]->selected_database ?? null;

        return is_string($selected) ? trim($selected) : '';
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
