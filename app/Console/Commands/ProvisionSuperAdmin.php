<?php

namespace App\Console\Commands;

use App\Rules\StrongPassword;
use App\Services\ProductionDatabaseTarget;
use App\Services\SuperAdminProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProvisionSuperAdmin extends Command
{
    protected $signature = 'admin:provision
        {--email= : Administrator email. Defaults to ADMIN_EMAIL}
        {--database= : Database name that must match the connection used for this account}
        {--confirm= : Type provision-super-admin to create the account}';

    protected $description = 'Create the single Super Admin. The password is read from ROOT_ADMIN_PASSWORD or a hidden prompt and is stored only as a hash.';

    public function handle(ProductionDatabaseTarget $targets): int
    {
        if ($this->option('confirm') !== 'provision-super-admin') {
            $this->error('Refused. Repeat with --confirm=provision-super-admin. No account was created.');

            return self::FAILURE;
        }

        $password = $this->suppliedPassword();
        if ($password === null) {
            $this->error('Refused. Set ROOT_ADMIN_PASSWORD or enter it at the hidden prompt. No account was created.');

            return self::FAILURE;
        }

        if (! StrongPassword::accepts($password)) {
            $this->forgetPassword();
            $this->error('Refused. The root password does not meet the strength rule. No account was created.');

            return self::FAILURE;
        }

        $requested = trim((string) $this->option('database'));
        if ($requested === '') {
            $this->forgetPassword();
            $this->error('Refused. Pass --database with the target database name. No account was created.');

            return self::FAILURE;
        }

        if ($targets->isProtected($requested)) {
            $this->forgetPassword();
            $this->error('Refused. '.$requested.' is protected. No account was created.');

            return self::FAILURE;
        }

        $previous = DB::getDefaultConnection();

        try {
            $this->selectDatabase($requested);
            $actual = (string) DB::connection()->getDatabaseName();
            if ($actual !== $requested) {
                throw new RuntimeException('Refused. The connection did not select the requested database. No account was created.');
            }
            if ($targets->isProtected($actual)) {
                throw new RuntimeException('Refused. '.$actual.' is protected. No account was created.');
            }

            $email = trim((string) ($this->option('email') ?: getenv('ADMIN_EMAIL') ?: 'admin@deltasupport.local'));
            app(SuperAdminProvisioner::class)->create($email, $password);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Refused. The Super Admin could not be created. No password was written to the log.');

            return self::FAILURE;
        } finally {
            $this->forgetPassword();
            DB::setDefaultConnection($previous);
        }

        $this->info('Super Admin created. Login ID ST666666. The password is stored only as a hash.');
        $this->line('Change it at first login. The password was not printed or written to a file.');

        return self::SUCCESS;
    }

    private function selectDatabase(string $requested): void
    {
        $driver = (string) DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $actual = (string) DB::connection()->getDatabaseName();
            if ($actual !== $requested) {
                throw new RuntimeException('Refused. The --database value does not match the connected database. No account was created.');
            }

            return;
        }

        $base = config('database.connections.mysql');
        if (! is_array($base)) {
            throw new RuntimeException('Refused. The MySQL connection is not configured. No account was created.');
        }

        $base['database'] = $requested;
        config(['database.connections.production_bootstrap' => $base]);
        DB::purge('production_bootstrap');
        DB::setDefaultConnection('production_bootstrap');
    }

    private function suppliedPassword(): ?string
    {
        $password = getenv('ROOT_ADMIN_PASSWORD');
        if (! is_string($password) || $password === '') {
            $fromServer = $_SERVER['ROOT_ADMIN_PASSWORD'] ?? '';
            $password = is_string($fromServer) ? $fromServer : '';
        }

        if ($password === '' && $this->input->isInteractive() && ! app()->runningUnitTests()) {
            $password = (string) $this->secret('Root admin password');
        }

        return $password === '' ? null : $password;
    }

    private function forgetPassword(): void
    {
        putenv('ROOT_ADMIN_PASSWORD');
        unset($_ENV['ROOT_ADMIN_PASSWORD'], $_SERVER['ROOT_ADMIN_PASSWORD']);
    }
}
