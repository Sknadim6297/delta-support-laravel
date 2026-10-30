<?php

namespace App\Console\Commands;

use App\Services\FinancialActivationGuard;
use App\Services\FinancialControls;
use App\Services\ProductionDatabaseTarget;
use Database\Seeders\ProductionDatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

class PrepareProductionDatabase extends Command
{
    protected $signature = 'production:prepare
        {--database= : New empty MySQL database name}
        {--confirm= : Type prepare-production-database}';

    protected $description = 'Create and migrate one new empty MySQL database, then seed essential configuration only.';

    public function handle(ProductionDatabaseTarget $targets): int
    {
        if ($this->option('confirm') !== 'prepare-production-database') {
            $this->error('Refused. Repeat with --confirm=prepare-production-database. No database was changed.');

            return self::FAILURE;
        }

        try {
            $name = $targets->assertFreshName((string) $this->option('database'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $configured = (string) config('database.connections.mysql.database');
        if ($targets->isProtected($configured) && strcasecmp($configured, $name) === 0) {
            $this->error('Refused. The configured database is protected. No database was changed.');

            return self::FAILURE;
        }

        $previous = DB::getDefaultConnection();

        try {
            $pdo = $this->server();
            $created = $this->ensureEmptyDatabase($pdo, $name);
            $this->selectDatabase($name);
            $actual = (string) DB::connection('production_bootstrap')->getDatabaseName();
            if ($actual !== $name) {
                throw new RuntimeException('Refused. The connection did not select '.$name.'. No migration was run.');
            }

            $tables = (int) DB::connection('production_bootstrap')->selectOne(
                'SELECT COUNT(*) AS table_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
                [$name]
            )->table_count;
            if ($tables > 0) {
                throw new RuntimeException('Refused. '.$name.' already has tables. No migration was run and nothing was deleted.');
            }

            $migrate = Artisan::call('migrate', [
                '--database' => 'production_bootstrap',
                '--force' => true,
            ]);
            if ($migrate !== 0) {
                throw new RuntimeException('Migrations did not finish. Nothing was deleted.');
            }

            $seed = Artisan::call('db:seed', [
                '--database' => 'production_bootstrap',
                '--class' => ProductionDatabaseSeeder::class,
                '--force' => true,
            ]);
            if ($seed !== 0) {
                throw new RuntimeException('Essential seeding did not finish. The schema was not deleted.');
            }

            $this->assertLaunchState($name);
            app(FinancialActivationGuard::class)->recordInitialization('production_bootstrap');
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Refused. The new database could not be prepared. No protected database was changed.');

            return self::FAILURE;
        } finally {
            DB::setDefaultConnection($previous);
            DB::purge('production_bootstrap');
        }

        $this->info(($created ? 'Created and prepared' : 'Prepared').' empty database '.$name.' on '.(string) config('database.connections.mysql.host').'.');
        $this->line('Members, purchases, investments, commissions, withdrawals, transfers, and wallet transactions are 0.');
        $this->line('Financial switches are off. The initialization marker is recorded and posting is not armed.');
        $this->line('No root password was created. Run admin:provision with ROOT_ADMIN_PASSWORD when this database is the approved target.');

        return self::SUCCESS;
    }

    private function server(): PDO
    {
        $host = (string) config('database.connections.mysql.host');
        $port = (string) config('database.connections.mysql.port');
        $username = (string) config('database.connections.mysql.username');
        $password = (string) config('database.connections.mysql.password');

        return new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    private function ensureEmptyDatabase(PDO $pdo, string $name): bool
    {
        $existing = $pdo->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $existing->execute([$name]);
        $found = $existing->fetchColumn();

        if ($found === false) {
            $pdo->exec(
                'CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );

            return true;
        }

        $tables = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
        $tables->execute([$name]);
        if ((int) $tables->fetchColumn() > 0) {
            throw new RuntimeException('Refused. '.$name.' already has tables. No migration was run and nothing was deleted.');
        }

        return false;
    }

    private function selectDatabase(string $name): void
    {
        $base = config('database.connections.mysql');
        if (! is_array($base)) {
            throw new RuntimeException('Refused. The MySQL connection is not configured. Nothing was changed.');
        }

        $base['database'] = $name;
        config(['database.connections.production_bootstrap' => $base]);
        DB::purge('production_bootstrap');
    }

    private function assertLaunchState(string $name): void
    {
        $connection = DB::connection('production_bootstrap');
        if ((string) $connection->getDatabaseName() !== $name) {
            throw new RuntimeException('Refused. Verification was not reading '.$name.'.');
        }

        $counts = [
            'members' => 'members',
            'users' => 'users',
            'investments' => 'investments',
            'package_purchases' => 'package_purchases',
            'roi_transactions' => 'roi_transactions',
            'level_commission_transactions' => 'level_commission_transactions',
            'package_level_accruals' => 'package_level_accruals',
            'package_roi_accruals' => 'package_roi_accruals',
            'investment_withdrawals' => 'investment_withdrawals',
            'withdrawal_requests' => 'withdrawal_requests',
            'member_transfers' => 'member_transfers',
            'wallet_ledger_entries' => 'wallet_ledger_entries',
            'activation_wallet_transactions' => 'activation_wallet_transactions',
            'support_tickets' => 'support_tickets',
        ];

        foreach ($counts as $label => $table) {
            if (! $connection->getSchemaBuilder()->hasTable($table)) {
                continue;
            }
            $count = (int) $connection->table($table)->count();
            if ($count !== 0) {
                throw new RuntimeException('Refused. '.$label.' contains '.$count.' rows. No further records were added.');
            }
        }

        $controls = $connection->table('financial_controls')->first();
        foreach (FinancialControls::FLAGS as $flag) {
            if ($controls === null || (int) $controls->{$flag} !== 0) {
                throw new RuntimeException('Refused. '.$flag.' is not disabled.');
            }
        }
        foreach (['financial_automation_armed', 'financial_emergency_stop'] as $flag) {
            if ($controls === null || (int) $controls->{$flag} !== 0) {
                throw new RuntimeException('Refused. '.$flag.' is not disabled.');
            }
        }

        $transfers = $connection->table('member_transfer_settings')->value('member_transfers_enabled');
        if ((int) $transfers !== 0) {
            throw new RuntimeException('Refused. Member transfers are not disabled.');
        }

        if ((int) $connection->table('commission_plan_levels')->where('is_active', true)->count() !== 0) {
            throw new RuntimeException('Refused. The 20-level commission plan is not inactive.');
        }
        if ((int) $connection->table('commission_plan_levels')->count() !== 20) {
            throw new RuntimeException('Refused. The commission plan does not contain 20 levels.');
        }
    }
}
