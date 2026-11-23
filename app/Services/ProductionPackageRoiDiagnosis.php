<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackageIncomeTerm;
use App\Models\PackagePurchase;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only production package / ROI / associate-posting diagnosis.
 * Never posts income, never mutates wallets, never writes config.
 */
class ProductionPackageRoiDiagnosis
{
    /** @var list<string> */
    public const SIX_CODES = OriginalTourPlanComparison::PRODUCTION_PACKAGE_CODES;

    public function __construct(
        private readonly PackageIncomePolicy $policy,
        private readonly AssociateCommissionStructure $associate,
        private readonly OriginalTourPlanComparison $originalPlan,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $plan = $this->originalPlan->preview();
        $packages = $this->packageRows();
        $missingIncome = [];
        $rootCauses = [];

        foreach (self::SIX_CODES as $code) {
            $row = $packages[$code] ?? null;
            $def = $this->policy->definition($code);
            $isShare = $code === 'DST-RY-006';
            if ($isShare) {
                if ($def !== null) {
                    $rootCauses[] = "DELTA SHARE {$code} unexpectedly has package_income ROI — must remain omitted.";
                }
                continue;
            }
            if ($def === null) {
                $missingIncome[] = $code;
                $rootCauses[] = "{$code}: Admin Daily ROI / ROI days show Not configured because config deltasupport.package_income.packages has no entry for this code.";
            }
            if (($row['exists'] ?? false) && $row['roi_eligible'] === false && $def === null) {
                $rootCauses[] = "{$code}: catalog roi_eligible=false (Active catalog status is independent of income eligibility).";
            }
            if (! ($row['exists'] ?? false)) {
                $rootCauses[] = "{$code}: package catalog row missing in this database (diagnosis does not create packages).";
            }
        }

        if ($missingIncome === []) {
            $rootCauses[] = 'All five ROI production codes have package_income entries in this environment.';
        } else {
            $rootCauses[] = 'Primary root cause of SILVER/VIP/DIAMOND Not configured: absent package_income.packages keys (engine reused; rates not activated). Candidate original_tour_plan_v1 is inactive and not merged.';
        }

        return [
            'mutates' => false,
            'generated_at' => now()->toIso8601String(),
            'database' => (string) config('database.connections.'.config('database.default').'.database'),
            'six_production_codes' => self::SIX_CODES,
            'packages' => array_values($packages),
            'package_income_keys' => array_keys(config('deltasupport.package_income.packages', [])),
            'missing_package_income_for_roi_tiers' => $missingIncome,
            'package_income_enabled' => $this->policy->enabled(),
            'tour_posting_authorized' => (bool) config('deltasupport.package_income.tour_posting_authorized', false),
            'wallet_ledger_enabled' => (bool) config('deltasupport.wallet_ledger.enabled', false),
            'wait_days' => (int) config('deltasupport.package_income.wait_days', 10),
            'original_tour_plan' => [
                'version_code' => $plan['version_code'],
                'active' => $plan['active'],
                'applies_to_new_purchases' => $plan['applies_to_new_purchases'],
                'candidate_codes' => array_keys($plan['candidate_package_income']),
                'candidate_merged' => $plan['candidate_merged_into_package_income'],
            ],
            'associate' => $this->associateStatus(),
            'verify_one' => $this->verifyOneNote($packages['VERIFY-1'] ?? null),
            'counts' => $this->counts(),
            'root_causes' => array_values(array_unique($rootCauses)),
            'read_only_production_commands' => [
                'php artisan financial:diagnose-production-packages',
                'php artisan financial:eligibility',
                'php artisan financial:status',
                'php artisan config:show deltasupport.associate_commission',
                'php artisan tinker --execute="dump(config(\'deltasupport.associate_commission.posting_enabled\'), env(\'DELTASUPPORT_ASSOCIATE_COMMISSION_POSTING\'));"',
            ],
            'activation_blockers' => [
                'Written approval of INR 104 = 1 USDT conversion and 4dp round-half-up candidate rates.',
                'Written decision on final-day rounding adjustments (do not invent adjustments).',
                'Written decision: historical approved purchases keep legacy snapshots; new version for future approvals only.',
                'Do not merge original_tour_plan.candidate_package_income into package_income.packages until approved.',
                'Do not set DELTASUPPORT_ASSOCIATE_COMMISSION_POSTING=true.',
                'Do not set DELTASUPPORT_ASSOCIATE_COMMISSION_SNAPSHOT=true.',
                'Clear or rebuild config cache after any env change: php artisan config:clear (then config:cache only if your deploy uses it).',
                'VERIFY-1 must never receive package_income ROI terms.',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function packageRows(): array
    {
        if (! Schema::hasTable('packages')) {
            return [];
        }

        $codes = array_merge(self::SIX_CODES, ['VERIFY-1']);
        $rows = Package::query()->whereIn('code', $codes)->get()->keyBy('code');
        $out = [];
        foreach ($codes as $code) {
            $pkg = $rows->get($code);
            $def = $this->policy->definition($code);
            $tier = $this->associate->resolveTierForPackageCode($code);
            $out[$code] = [
                'code' => $code,
                'exists' => $pkg !== null,
                'name' => $pkg?->name,
                'price_usd' => $pkg !== null ? bcadd((string) $pkg->price_usd, '0', 4) : null,
                'is_active' => $pkg !== null ? (bool) $pkg->is_active : null,
                'roi_eligible' => $pkg !== null ? (bool) $pkg->roi_eligible : null,
                'level_commission_eligible' => $pkg !== null ? (bool) $pkg->level_commission_eligible : null,
                'package_income' => $def,
                'admin_daily_roi_label' => $def ? $def['roi_daily'].' USDT' : 'Not configured',
                'admin_roi_days_label' => $def ? (string) $def['roi_days'] : 'Not configured',
                'associate_tier' => is_string($tier) ? $tier : null,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function associateStatus(): array
    {
        $envRaw = env('DELTASUPPORT_ASSOCIATE_COMMISSION_POSTING');
        $configCached = false;
        try {
            $configCached = file_exists(base_path('bootstrap/cache/config.php'));
        } catch (\Throwable) {
            $configCached = false;
        }

        return [
            'active_version' => (string) config('deltasupport.associate_commission.active_version'),
            'posting_enabled_config' => $this->associate->postingEnabled(),
            'snapshot_on_approval' => $this->associate->snapshotOnApprovalEnabled(),
            'env_DELTASUPPORT_ASSOCIATE_COMMISSION_POSTING' => $envRaw,
            'env_interpreted_bool' => filter_var($envRaw, FILTER_VALIDATE_BOOLEAN),
            'config_cache_file_present' => $configCached,
            'ui_source' => 'Admin ROI Plans / Packages / Commission Settings read AssociateCommissionStructure::postingEnabled() → config(deltasupport.associate_commission.posting_enabled) only (not DB).',
            'discrepancy_explanation' => 'If Admin shows Posting ENABLED while earlier verification expected false: production .env has DELTASUPPORT_ASSOCIATE_COMMISSION_POSTING=true and/or the Laravel config cache (bootstrap/cache/config.php) was built with true. Local default is false. Accrual still uses legacy commission_pool×% until associate posting path is separately approved — but the UI label is truthful to config and must not be ignored.',
            'do_not_enable' => true,
            'tier_package_codes' => config('deltasupport.associate_commission.tier_package_codes'),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $verify
     * @return array<string, mixed>
     */
    private function verifyOneNote(?array $verify): array
    {
        return [
            'code' => 'VERIFY-1',
            'exists' => (bool) ($verify['exists'] ?? false),
            'is_active' => $verify['is_active'] ?? null,
            'roi_eligible' => $verify['roi_eligible'] ?? null,
            'has_package_income' => $this->policy->definition('VERIFY-1') !== null,
            'note' => 'Migration 2026_11_23_140000 may set VERIFY-1 is_active=true for catalog listing. That is verification-only, not income activation. Follow-up migration activates the six production tour codes without creating packages or writing package_income.',
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function counts(): array
    {
        $counts = [
            'packages' => Schema::hasTable('packages') ? Package::query()->count() : 0,
            'purchases' => Schema::hasTable('package_purchases') ? PackagePurchase::query()->count() : 0,
            'approved_purchases' => Schema::hasTable('package_purchases')
                ? PackagePurchase::query()->where('status', PackagePurchase::APPROVED)->count()
                : 0,
            'income_terms' => Schema::hasTable('package_income_terms') ? PackageIncomeTerm::query()->count() : 0,
            'wallet_ledger_entries' => Schema::hasTable('wallet_ledger_entries') ? WalletLedgerEntry::query()->count() : 0,
        ];

        foreach (self::SIX_CODES as $code) {
            $counts['approved_'.$code] = Schema::hasTable('package_purchases')
                ? PackagePurchase::query()->where('package_code', $code)->where('status', PackagePurchase::APPROVED)->count()
                : 0;
        }

        return $counts;
    }
}
