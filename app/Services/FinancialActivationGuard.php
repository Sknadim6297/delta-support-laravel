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

    public function refusal(string $requested, bool $allowExistingBusiness = false): ?string
    {
        try {
            return $this->assess($requested, $allowExistingBusiness);
        } catch (Throwable) {
            return 'Refused. The production initialization state is uncertain. No posting was armed.';
        }
    }

    private function assess(string $requested, bool $allowExistingBusiness = false): ?string
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

        $planIssues = $this->inactivePlanIssues($controls);
        if ($planIssues !== []) {
            return 'Refused. The financial configuration is not the approved inactive plan. Mismatches: '
                .implode(' | ', $planIssues)
                .' No posting was armed.';
        }

        if ($this->hasBusinessRecords() && ! $allowExistingBusiness) {
            return 'Refused. This database already has business records. No posting was armed.';
        }

        return null;
    }

    /**
     * Exact inactive-plan mismatches. Empty means the approved inactive plan is satisfied.
     *
     * @return list<string>
     */
    public function inactivePlanIssues(?object $controls = null): array
    {
        try {
            if ($controls === null) {
                if (! Schema::hasTable('financial_controls')) {
                    return ['financial_controls table is missing.'];
                }
                $controls = DB::table('financial_controls')->first();
            }
            if ($controls === null) {
                return ['financial_controls row is missing.'];
            }

            return $this->collectInactivePlanIssues($controls);
        } catch (Throwable) {
            return ['The inactive-plan inspection failed because the schema state is uncertain.'];
        }
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
        return $this->collectInactivePlanIssues($controls) === [];
    }

    /**
     * @return list<string>
     */
    private function collectInactivePlanIssues(object $controls): array
    {
        $issues = [];

        foreach (FinancialControls::FLAGS as $flag) {
            if ((int) ($controls->{$flag} ?? 0) !== 0) {
                $issues[] = 'financial_controls.'.$flag.' must be 0 (inactive) before arming.';
            }
        }

        if (! Schema::hasTable('member_transfer_settings')) {
            $issues[] = 'member_transfer_settings table is missing.';
        } elseif ((int) DB::table('member_transfer_settings')->value('member_transfers_enabled') !== 0) {
            $issues[] = 'member_transfer_settings.member_transfers_enabled must be 0.';
        }

        if (! Schema::hasTable('commission_plan_levels')) {
            $issues[] = 'commission_plan_levels table is missing.';
        } else {
            $levels = DB::table('commission_plan_levels')
                ->where('plan_code', CommissionPlanLevel::PLAN_CODE)
                ->orderBy('level')
                ->get();
            if ($levels->count() !== 20) {
                $issues[] = 'commission_plan_levels must contain exactly 20 rows for plan '.CommissionPlanLevel::PLAN_CODE.' (found '.$levels->count().').';
            }
            if ($levels->contains(fn (object $level): bool => (int) $level->is_active !== 0)) {
                $issues[] = 'commission_plan_levels must all stay inactive (is_active=0).';
            }
            foreach ($levels as $level) {
                $expected = CommissionPlan::RATE_BY_LEVEL[(int) $level->level] ?? null;
                if ($expected === null) {
                    $issues[] = 'commission_plan_levels has unexpected level '.(int) $level->level.'.';
                } elseif (bccomp((string) $level->percentage, $expected, 4) !== 0) {
                    $issues[] = 'commission_plan_levels level '.(int) $level->level.' must be '.$expected.' (found '.(string) $level->percentage.').';
                }
            }
            foreach (array_keys(CommissionPlan::RATE_BY_LEVEL) as $requiredLevel) {
                if (! $levels->contains(fn (object $level): bool => (int) $level->level === $requiredLevel)) {
                    $issues[] = 'commission_plan_levels is missing level '.$requiredLevel.'.';
                }
            }
        }

        $demoIssues = $this->demoCatalogIssues();
        $productionIssues = $this->productionCatalogIssues();
        if ($demoIssues !== [] && $productionIssues !== []) {
            $issues[] = 'Neither the demo catalog nor the production catalog satisfies the approved inactive plan.';
            foreach (array_slice($productionIssues, 0, 8) as $issue) {
                $issues[] = $issue;
            }
            if (count($productionIssues) > 8) {
                $issues[] = (count($productionIssues) - 8).' more production-catalog issues omitted.';
            }
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function demoCatalogIssues(): array
    {
        $issues = [];
        $packages = [
            'DEMO-COAST' => '100.0000',
            'DEMO-HILLS' => '1000.0000',
        ];
        foreach ($packages as $code => $price) {
            $package = DB::table('packages')->where('code', $code)->first();
            if ($package === null) {
                $issues[] = 'Demo package '.$code.' is missing.';
                continue;
            }
            if ((int) $package->roi_eligible !== 1 || (int) $package->level_commission_eligible !== 1) {
                $issues[] = 'Demo package '.$code.' must have roi_eligible=1 and level_commission_eligible=1.';
            }
            if (bccomp((string) $package->price_usd, $price, 4) !== 0) {
                $issues[] = 'Demo package '.$code.' price must be '.$price.' (found '.(string) $package->price_usd.').';
            }
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function productionCatalogIssues(): array
    {
        $issues = [];
        $roi = DB::table('packages')->where('code', 'DST-ROI-002')->first();
        if ($roi === null) {
            $issues[] = 'Production package DST-ROI-002 is missing.';
        } else {
            if ((int) $roi->roi_eligible !== 1) {
                $issues[] = 'DST-ROI-002 roi_eligible must be 1.';
            }
            if ((int) $roi->level_commission_eligible !== 1) {
                $issues[] = 'DST-ROI-002 level_commission_eligible must be 1.';
            }
            if (bccomp((string) $roi->price_usd, '100.0000', 4) !== 0) {
                $issues[] = 'DST-ROI-002 price must be 100.0000 (found '.(string) $roi->price_usd.').';
            }
        }

        $definition = config('deltasupport.package_income.packages.DST-ROI-002');
        if (! is_array($definition)) {
            $issues[] = 'config deltasupport.package_income.packages.DST-ROI-002 is missing. Deploy the production package income map.';
        } else {
            if (bccomp((string) ($definition['roi_daily'] ?? '0'), '0.2500', 4) !== 0) {
                $issues[] = 'DST-ROI-002 config roi_daily must be 0.2500.';
            }
            if ((int) ($definition['roi_days'] ?? 0) !== 800) {
                $issues[] = 'DST-ROI-002 config roi_days must be 800.';
            }
            if (bccomp((string) ($definition['commission_pool'] ?? '0'), '0.2400', 4) !== 0) {
                $issues[] = 'DST-ROI-002 config commission_pool must be 0.2400.';
            }
            if ((int) ($definition['commission_days'] ?? 0) !== 400) {
                $issues[] = 'DST-ROI-002 config commission_days must be 400.';
            }
        }

        if (config('deltasupport.package_income.packages.DST-TOUR-003') !== null) {
            $issues[] = 'DST-TOUR-003 must not have a package_income config definition (tour-only).';
        }

        $tour = DB::table('packages')->where('code', 'DST-TOUR-003')->first();
        if ($tour !== null) {
            if ((int) $tour->roi_eligible !== 0) {
                $issues[] = 'DST-TOUR-003 roi_eligible must be 0 (tour-only).';
            }
            if ((int) $tour->level_commission_eligible !== 0) {
                $issues[] = 'DST-TOUR-003 level_commission_eligible must be 0 (tour-only).';
            }
            if (bccomp((string) $tour->price_usd, '1000.0000', 4) !== 0) {
                $issues[] = 'DST-TOUR-003 price must be 1000.0000 (found '.(string) $tour->price_usd.').';
            }
        }

        return $issues;
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
