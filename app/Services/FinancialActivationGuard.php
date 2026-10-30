<?php

namespace App\Services;

use App\Models\CommissionPlanLevel;
use App\Services\CommissionPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class FinancialActivationGuard
{
    public const MARKER = 'delta-support-production-initialized';

    /**
     * @var list<string>
     */
    private const BUSINESS_TABLES = [
        'members',
        'package_purchases',
        'package_roi_accruals',
        'package_level_accruals',
        'roi_transactions',
        'level_commission_transactions',
        'wallet_ledger_entries',
        'withdrawal_requests',
        'member_transfers',
    ];

    public function recordInitialization(string $connection): void
    {
        $identity = $this->identity($connection);
        if ($identity === null) {
            throw new RuntimeException('Refused. The database identity is uncertain. No initialization marker was written and no posting was armed.');
        }

        DB::connection($connection)->table('production_initializations')->updateOrInsert(
            ['marker' => self::MARKER],
            [
                'driver' => $identity['driver'],
                'host' => $identity['host'],
                'port' => $identity['port'],
                'database_name' => $identity['database'],
                'initialized_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function refusal(string $requested): ?string
    {
        try {
            return $this->assess($requested);
        } catch (Throwable) {
            return 'Refused. The production initialization state is uncertain. No posting was armed.';
        }
    }

    private function assess(string $requested): ?string
    {
        $requested = trim($requested);
        if ($requested === '') {
            return 'Refused. The database was not named. No posting was armed.';
        }

        $identity = $this->identity(DB::getDefaultConnection());
        $approved = trim((string) config('deltasupport.financial_approved_database', ''));

        if ($identity === null || $requested === '' || $approved === '' || $requested !== $identity['database'] || $requested !== $approved) {
            return 'Refused. '.$requested.' is not the approved target database. No posting was armed.';
        }

        if (app(ProductionDatabaseTarget::class)->isProtected($requested)) {
            return 'Refused. '.$requested.' is not the approved target database. No posting was armed.';
        }

        if (! $this->schemaReady()) {
            return 'Refused. The production initialization state is uncertain. No posting was armed.';
        }

        if (! $this->markerMatches($identity)) {
            return 'Refused. The database identity does not match the initialization marker. No posting was armed.';
        }

        $controls = DB::table('financial_controls')->first();
        if ($controls === null) {
            return 'Refused. The production initialization state is uncertain. No posting was armed.';
        }

        if ((int) $controls->financial_emergency_stop !== 0) {
            return 'Refused. The emergency stop is on. No posting was armed.';
        }

        if ((int) $controls->financial_automation_armed !== 0) {
            return 'Refused. Automatic posting is already armed. No change was made.';
        }

        if (! $this->configurationApproved($controls)) {
            return 'Refused. The financial configuration is not the approved inactive plan. No posting was armed.';
        }

        if ($this->hasBusinessRecords()) {
            return 'Refused. This database already has business records. No posting was armed.';
        }

        return null;
    }

    /**
     * @return array{driver: string, host: string, port: string, database: string}|null
     */
    private function identity(string $connection): ?array
    {
        $database = DB::connection($connection)->getDatabaseName();
        $config = DB::connection($connection)->getConfig();
        $driver = trim((string) ($config['driver'] ?? ''));
        if (! is_string($database) || trim($database) === '' || $driver === '') {
            return null;
        }

        return [
            'driver' => $driver,
            'host' => trim((string) ($config['host'] ?? '')),
            'port' => trim((string) ($config['port'] ?? '')),
            'database' => trim($database),
        ];
    }

    private function schemaReady(): bool
    {
        foreach (['financial_controls', 'production_initializations', 'commission_plan_levels', 'packages', 'member_transfer_settings', ...self::BUSINESS_TABLES] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return Schema::hasColumn('financial_controls', 'financial_automation_armed')
            && Schema::hasColumn('financial_controls', 'financial_emergency_stop');
    }

    /**
     * @param  array{driver: string, host: string, port: string, database: string}  $identity
     */
    private function markerMatches(array $identity): bool
    {
        $rows = DB::table('production_initializations')->where('marker', self::MARKER)->get();
        if ($rows->count() !== 1) {
            return false;
        }

        $row = $rows->first();

        return (string) $row->driver === $identity['driver']
            && (string) $row->host === $identity['host']
            && (string) $row->port === $identity['port']
            && (string) $row->database_name === $identity['database'];
    }

    private function configurationApproved(object $controls): bool
    {
        foreach (FinancialControls::FLAGS as $flag) {
            if ((int) $controls->{$flag} !== 0) {
                return false;
            }
        }

        if ((int) DB::table('member_transfer_settings')->value('member_transfers_enabled') !== 0) {
            return false;
        }

        $levels = DB::table('commission_plan_levels')
            ->where('plan_code', CommissionPlanLevel::PLAN_CODE)
            ->orderBy('level')
            ->get();
        if ($levels->count() !== 20 || $levels->contains(fn (object $level): bool => (int) $level->is_active !== 0)) {
            return false;
        }

        foreach ($levels as $level) {
            $expected = CommissionPlan::RATE_BY_LEVEL[(int) $level->level] ?? null;
            if ($expected === null || bccomp((string) $level->percentage, $expected, 4) !== 0) {
                return false;
            }
        }

        $packages = [
            'DEMO-COAST' => '100.0000',
            'DEMO-HILLS' => '1000.0000',
        ];
        foreach ($packages as $code => $price) {
            $package = DB::table('packages')->where('code', $code)->first();
            if ($package === null || (int) $package->roi_eligible !== 1 || (int) $package->level_commission_eligible !== 1) {
                return false;
            }
            if (bccomp((string) $package->price_usd, $price, 4) !== 0) {
                return false;
            }
        }

        return true;
    }

    private function hasBusinessRecords(): bool
    {
        foreach (self::BUSINESS_TABLES as $table) {
            if ((int) DB::table($table)->count() !== 0) {
                return true;
            }
        }

        return false;
    }
};
