<?php

namespace App\Services;

use App\Models\PackageIncomeActivation;
use App\Models\PackagePurchase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

/**
 * Forward-only income activation for packages that require an explicit business date.
 *
 * Duration rule (documented, not silently restarted):
 * - The ROI/commission window still starts from the purchase's normal first accrual
 *   date (reviewed_at Asia/Kolkata start + wait_days) and lasts roi_days /
 *   commission_days from that original first day.
 * - The package activation date is a posting floor. Accruals may only post on
 *   business dates >= max(original first accrual, activation date) and still
 *   inside the original window.
 * - Purchases whose original window ended before activation earn nothing.
 * - Missing activation for a gated package fails closed (no income planned).
 */
class PackageIncomeActivationGate
{
    public const TOUR_CODE = 'DST-TOUR-003';

    /**
     * @return list<string>
     */
    public function gatedPackageCodes(): array
    {
        $configured = config('deltasupport.package_income.activation_required_packages', [self::TOUR_CODE]);

        return is_array($configured) ? array_values(array_map('strval', $configured)) : [self::TOUR_CODE];
    }

    public function requiresActivation(string $packageCode): bool
    {
        return in_array($packageCode, $this->gatedPackageCodes(), true);
    }

    public function activationBusinessDate(string $packageCode): ?CarbonImmutable
    {
        if (! $this->requiresActivation($packageCode)) {
            return null;
        }
        if (! Schema::hasTable('package_income_activations')) {
            return null;
        }

        $row = PackageIncomeActivation::query()->where('package_code', $packageCode)->first();
        if ($row === null || $row->activation_business_date === null) {
            return null;
        }

        return CarbonImmutable::parse($row->activation_business_date->toDateString(), $this->timezone())->startOfDay();
    }

    public function isActivated(string $packageCode): bool
    {
        return $this->activationBusinessDate($packageCode) !== null;
    }

    /**
     * Original first accrual from approval + wait. Unaffected by activation.
     */
    public function originalFirstAccrualDate(PackagePurchase $purchase, PackageIncomePolicy $policy): ?CarbonImmutable
    {
        return $policy->firstAccrualDate($purchase);
    }

    /**
     * First business date that may receive income for this purchase.
     * Null means fail closed for gated packages, or missing approval/wait data.
     */
    public function firstAllowedIncomeDate(PackagePurchase $purchase, PackageIncomePolicy $policy): ?CarbonImmutable
    {
        $original = $this->originalFirstAccrualDate($purchase, $policy);
        if ($original === null) {
            return null;
        }

        if (! $this->requiresActivation($purchase->package_code)) {
            return $original;
        }

        $activation = $this->activationBusinessDate($purchase->package_code);
        if ($activation === null) {
            return null;
        }

        return $original->greaterThan($activation) ? $original : $activation;
    }

    public function allowsBusinessDate(PackagePurchase $purchase, CarbonImmutable $businessDate, PackageIncomePolicy $policy): bool
    {
        $allowed = $this->firstAllowedIncomeDate($purchase, $policy);
        if ($allowed === null) {
            return false;
        }

        return $businessDate->greaterThanOrEqualTo($allowed);
    }

    public function timezone(): string
    {
        return (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
    }
}
