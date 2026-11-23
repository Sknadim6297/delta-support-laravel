<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackageIncomeTerm;
use App\Models\PackagePlanVersion;
use App\Models\PackagePurchase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit source-selection for daily ROI definitions.
 *
 * Catalog (forPackageCode) — future purchases / Auto ROI status:
 * 1) Legacy config when package.income_source=legacy_config and a config row exists
 * 2) Published DB version with accrual_authority when income_source=database
 *
 * Purchase (forPurchase) — immutable entitlement, never invents history:
 * 1) Valid income-term ROI snapshot
 * 2) Purchase-linked package_plan_version
 * 3) Legacy config code only (GOLD/PREMIUM historical path)
 * 4) Otherwise null — never unrestricted current-catalog fallback
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

        $mode = (string) ($package->income_source ?? Package::INCOME_SOURCE_LEGACY);

        if ($mode === Package::INCOME_SOURCE_LEGACY) {
            if ($legacy !== null) {
                return $legacy + [
                    'source' => self::SOURCE_LEGACY_CONFIG,
                    'package_plan_version_id' => null,
                ];
            }

            // legacy_config with no config row: catalog stays closed until an authorized DB cutover.
            return null;
        }

        if ($mode === Package::INCOME_SOURCE_DATABASE) {
            $version = $this->authoritativeVersion($package);
            $definition = $version?->asIncomeDefinition();
            if ($definition !== null) {
                return $definition + [
                    'source' => self::SOURCE_DATABASE,
                    'package_plan_version_id' => $version->id,
                ];
            }

            return null;
        }

        // Unknown mode: prefer legacy when present, else authorized DB.
        if ($legacy !== null) {
            return $legacy + [
                'source' => self::SOURCE_LEGACY_CONFIG,
                'package_plan_version_id' => null,
            ];
        }

        $version = $this->authoritativeVersion($package);
        $definition = $version?->asIncomeDefinition();
        if ($definition !== null) {
            return $definition + [
                'source' => self::SOURCE_DATABASE,
                'package_plan_version_id' => $version->id,
            ];
        }

        return null;
    }

    /**
     * Purchase-specific entitlement. Does not use the current package catalog as a
     * fallback for approved purchases that lack a snapshot or linked plan version,
     * except for codes that still resolve from immutable legacy config.
     *
     * @return array{roi_daily: string, roi_days: int, commission_pool: ?string, commission_days: ?int, source: string, package_plan_version_id: ?int}|null
     */
    public function forPurchase(PackagePurchase $purchase): ?array
    {
        $term = $this->incomeTermRow($purchase);
        if ($term !== null) {
            $fromTerm = $this->definitionFromIncomeTerm($term);
            if ($fromTerm !== null) {
                return $fromTerm;
            }
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

        // Income-term row exists but ROI fields are empty/invalid: locked without ROI.
        // Do not inherit a later catalog authorization.
        if ($term !== null) {
            return null;
        }

        // No term and no linked version: only legacy config codes (historical GOLD/PREMIUM).
        $legacy = $this->legacyConfig((string) $purchase->package_code);
        if ($legacy !== null) {
            return $legacy + [
                'source' => self::SOURCE_LEGACY_CONFIG,
                'package_plan_version_id' => null,
            ];
        }

        return null;
    }

    /**
     * Catalog definition for NEW snapshot recording after a plan version was attached.
     * Prefer the purchase-linked version; otherwise the current authorized catalog.
     *
     * @return array{roi_daily: string, roi_days: int, commission_pool: ?string, commission_days: ?int, source: string, package_plan_version_id: ?int}|null
     */
    public function forNewApprovalSnapshot(PackagePurchase $purchase): ?array
    {
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

    public function authoritativeVersion(Package $package): ?PackagePlanVersion
    {
        $version = $this->activeVersionForPackage($package);
        if ($version === null || ! $version->accrual_authority) {
            return null;
        }

        return $version;
    }

    /**
     * Approved purchases that lack a clear ROI entitlement (for admin review).
     * Does not invent terms. Does not credit wallets.
     *
     * @return Collection<int, array{purchase_id: int, reference: string, member_id: string, reason: string}>
     */
    public function ambiguousApprovedPurchases(Package $package): Collection
    {
        return PackagePurchase::query()
            ->where('package_id', $package->id)
            ->where('status', PackagePurchase::APPROVED)
            ->orderBy('id')
            ->get()
            ->map(function (PackagePurchase $purchase): ?array {
                if ($this->forPurchase($purchase) !== null) {
                    return null;
                }

                $term = $this->incomeTermRow($purchase);
                $reason = 'no_roi_entitlement';
                if ($term === null && ! $purchase->package_plan_version_id) {
                    $reason = 'missing_snapshot_and_plan_version';
                } elseif ($term !== null && ($term->monthly_amount === null || $term->duration_months === null)) {
                    $reason = 'income_term_without_roi_fields';
                } elseif ($purchase->package_plan_version_id) {
                    $reason = 'linked_plan_version_has_no_roi';
                }

                return [
                    'purchase_id' => (int) $purchase->id,
                    'reference' => (string) $purchase->reference,
                    'member_id' => (string) $purchase->member_id,
                    'reason' => $reason,
                ];
            })
            ->filter()
            ->values();
    }

    private function incomeTermRow(PackagePurchase $purchase): ?PackageIncomeTerm
    {
        if (! Schema::hasTable('package_income_terms')) {
            return null;
        }

        return PackageIncomeTerm::query()->where('package_purchase_id', $purchase->id)->first();
    }

    /**
     * @return array{roi_daily: string, roi_days: int, commission_pool: ?string, commission_days: ?int, source: string, package_plan_version_id: ?int}|null
     */
    private function definitionFromIncomeTerm(PackageIncomeTerm $term): ?array
    {
        if ($term->monthly_amount === null || $term->duration_months === null || (int) $term->duration_months < 1) {
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
