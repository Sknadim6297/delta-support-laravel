<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackagePurchase;
use Carbon\CarbonImmutable;

class PackageIncomePolicy
{
    public function __construct(private readonly PackageIncomeDefinitionResolver $resolver)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('deltasupport.package_income.enabled', false);
    }

    public function adminRootId(): string
    {
        return (string) config('deltasupport.package_income.admin_root_id', 'ST666666');
    }

    /**
     * Current package definition for catalog / eligibility display.
     * Prefer purchase snapshots via definitionForPurchase() inside accrual.
     *
     * @return array{roi_daily: string, roi_days: int, commission_pool: ?string, commission_days: ?int, source?: string, package_plan_version_id?: ?int}|null
     */
    public function definition(string $packageCode): ?array
    {
        return $this->resolver->forPackageCode($packageCode);
    }

    /**
     * @return array{roi_daily: string, roi_days: int, commission_pool: ?string, commission_days: ?int, source?: string, package_plan_version_id?: ?int}|null
     */
    public function definitionForPurchase(PackagePurchase $purchase): ?array
    {
        return $this->resolver->forPurchase($purchase);
    }

    public function roiEligible(PackagePurchase $purchase, ?Package $package): bool
    {
        return $this->approved($purchase)
            && (bool) $package?->roi_eligible
            && $this->definitionForPurchase($purchase) !== null;
    }

    public function levelSourceEligible(PackagePurchase $purchase, ?Package $package): bool
    {
        $definition = $this->definitionForPurchase($purchase);

        return $this->approved($purchase)
            && (bool) $package?->level_commission_eligible
            && $definition !== null
            && $definition['commission_pool'] !== null
            && $definition['commission_days'] !== null
            && $definition['commission_days'] > 0;
    }

    public function qualifiesAsDirect(PackagePurchase $purchase, ?Package $package): bool
    {
        if ($purchase->status !== PackagePurchase::APPROVED || ! $package?->level_commission_eligible) {
            return false;
        }

        return in_array($purchase->package_type, ReferralLevelEligibility::QUALIFYING_PACKAGE_TYPES, true)
            || $this->definitionForPurchase($purchase) !== null
            || $this->definition($purchase->package_code) !== null;
    }

    public function approvalDate(PackagePurchase $purchase): ?CarbonImmutable
    {
        if ($purchase->reviewed_at === null) {
            return null;
        }

        return CarbonImmutable::parse($purchase->reviewed_at)
            ->timezone(config('deltasupport.package_income.timezone'))
            ->startOfDay();
    }

    public function firstAccrualDate(PackagePurchase $purchase): ?CarbonImmutable
    {
        $wait = (int) config('deltasupport.package_income.wait_days', 10);
        if ($purchase->package_plan_version_id) {
            $version = $purchase->planVersion;
            if ($version && $version->wait_days !== null) {
                $wait = (int) $version->wait_days;
            }
        }

        return $this->approvalDate($purchase)?->addDays($wait);
    }

    public function lastAccrualDate(CarbonImmutable $first, int $days): ?CarbonImmutable
    {
        if ($days < 1) {
            return null;
        }

        return $first->addDays($days - 1);
    }

    public function withinWindow(CarbonImmutable $businessDate, CarbonImmutable $first, int $days): bool
    {
        $last = $this->lastAccrualDate($first, $days);
        if ($last === null) {
            return false;
        }

        return $businessDate->greaterThanOrEqualTo($first) && $businessDate->lessThanOrEqualTo($last);
    }

    public function levelAmount(string $pool, int $level): ?string
    {
        $rate = CommissionPlan::RATE_BY_LEVEL[$level] ?? null;
        if ($rate === null) {
            return null;
        }

        return bcdiv(bcmul($pool, $rate, 4), '100', 4);
    }

    private function approved(PackagePurchase $purchase): bool
    {
        return $purchase->status === PackagePurchase::APPROVED && $purchase->reviewed_at !== null;
    }
}
