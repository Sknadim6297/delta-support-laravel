<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackageIncomeTerm;
use App\Models\PackagePlanVersion;
use App\Models\PackagePurchase;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit source-selection for daily ROI definitions.
 *
 * Order for a purchase:
 * 1) Immutable income-term snapshot (monthly_amount / duration_months)
 * 2) Purchase-linked package_plan_version
 * 3) Legacy config when package.income_source=legacy_config or code exists in config (preferred)
 * 4) Published DB version with accrual_authority when package.income_source=database
 *
 * Never credits wallets. Never enables associate posting.
 */
class PackageIncomeDefinitionResolver
{
    public const SOURCE_SNAPSHOT = 'purchase_snapshot';

    public const SOURCE_PURCHASE_VERSION = 'purchase_plan_version';

    public const SOURCE_LEGACY_CONFIG = 'legacy_config';

    public const SOURCE_DATABASE = 'database_plan_version';

    /**
     * @return array{roi_daily: string, roi_days: int, commission_pool: ?string, commission_days: ?int, source: string, package_plan_version_id: ?int}|null
     */
    public function forPackageCode(string $packageCode): ?array
    {
        $package = Package::query()->where('code', $packageCode)->first();
        $legacy = $this->legacyConfig($packageCode);

        if ($package === null) {
            return $legacy === null ? null : $legacy + [
                'source' => self::SOURCE_LEGACY_CONFIG,
                'package_plan_version_id' => null,
            ];
        }

        $mode = (string) ($package->income_source ?? 'legacy_config');
        if ($mode === 'legacy_config' || $legacy !== null && $mode !== 'database') {
            if ($legacy !== null) {
                return $legacy + [
                    'source' => self::SOURCE_LEGACY_CONFIG,
                    'package_plan_version_id' => null,
                ];
            }
        }

        if ($mode === 'database' || ($legacy === null && $mode !== 'legacy_config')) {
            $version = $this->authoritativeVersion($package);
            $definition = $version?->asIncomeDefinition();
            if ($definition !== null) {
                return $definition + [
                    'source' => self::SOURCE_DATABASE,
                    'package_plan_version_id' => $version->id,
                ];
            }
        }

        if ($legacy !== null) {
            return $legacy + [
                'source' => self::SOURCE_LEGACY_CONFIG,
                'package_plan_version_id' => null,
            ];
        }

        return null;
    }

    /**
     * @return array{roi_daily: string, roi_days: int, commission_pool: ?string, commission_days: ?int, source: string, package_plan_version_id: ?int}|null
     */
    public function forPurchase(PackagePurchase $purchase): ?array
    {
        $snapshot = $this->fromIncomeTerm($purchase);
        if ($snapshot !== null) {
            return $snapshot;
        }

        if (Schema::hasColumn('package_purchases', 'package_plan_version_id')
            && $purchase->package_plan_version_id) {
            $version = PackagePlanVersion::query()->find($purchase->package_plan_version_id);
            $definition = $version?->asIncomeDefinition();
            if ($definition !== null) {
                return $definition + [
                    'source' => self::SOURCE_PURCHASE_VERSION,
                    'package_plan_version_id' => $version->id,
                ];
            }
        }

        return $this->forPackageCode((string) $purchase->package_code);
    }

    public function activeVersionForPackage(Package $package): ?PackagePlanVersion
    {
        if ($package->active_plan_version_id) {
            $version = PackagePlanVersion::query()
                ->whereKey($package->active_plan_version_id)
                ->where('package_id', $package->id)
                ->where('status', PackagePlanVersion::STATUS_PUBLISHED)
                ->first();
            if ($version) {
                return $version;
            }
        }

        return PackagePlanVersion::query()
            ->where('package_id', $package->id)
            ->where('status', PackagePlanVersion::STATUS_PUBLISHED)
            ->orderByDesc('version_number')
            ->first();
    }

    private function authoritativeVersion(Package $package): ?PackagePlanVersion
    {
        $version = $this->activeVersionForPackage($package);
        if ($version === null || ! $version->accrual_authority) {
            return null;
        }

        return $version;
    }

    /**
     * @return array{roi_daily: string, roi_days: int, commission_pool: ?string, commission_days: ?int, source: string, package_plan_version_id: ?int}|null
     */
    private function fromIncomeTerm(PackagePurchase $purchase): ?array
    {
        if (! Schema::hasTable('package_income_terms')) {
            return null;
        }

        $term = PackageIncomeTerm::query()->where('package_purchase_id', $purchase->id)->first();
        if ($term === null || $term->monthly_amount === null || $term->duration_months === null || (int) $term->duration_months < 1) {
            return null;
        }

        return [
            'roi_daily' => bcadd((string) $term->monthly_amount, '0', 4),
            'roi_days' => (int) $term->duration_months,
            'commission_pool' => $term->commission_base === null || $term->commission_base === ''
                ? null
                : bcadd((string) $term->commission_base, '0', 4),
            'commission_days' => null,
            'source' => self::SOURCE_SNAPSHOT,
            'package_plan_version_id' => $term->package_plan_version_id ? (int) $term->package_plan_version_id : null,
        ];
    }

    /**
     * @return array{roi_daily: string, roi_days: int, commission_pool: ?string, commission_days: ?int}|null
     */
    private function legacyConfig(string $packageCode): ?array
    {
        $rules = config('deltasupport.package_income.packages.'.$packageCode);
        if (! is_array($rules) || ! isset($rules['roi_daily'], $rules['roi_days'])) {
            return null;
        }

        $hasPool = array_key_exists('commission_pool', $rules) && $rules['commission_pool'] !== null;

        return [
            'roi_daily' => bcadd((string) $rules['roi_daily'], '0', 4),
            'roi_days' => (int) $rules['roi_days'],
            'commission_pool' => $hasPool ? bcadd((string) $rules['commission_pool'], '0', 4) : null,
            'commission_days' => array_key_exists('commission_days', $rules) && $rules['commission_days'] !== null
                ? (int) $rules['commission_days']
                : null,
        ];
    }
}
