<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackagePurchase;
use Illuminate\Database\Query\Builder;

/**
 * Business-volume eligibility for package purchases.
 *
 * Intentionally separate from ReferralLevelEligibility::qualifyingPurchaseConstraint()
 * (income / unlock / recipient pay). Tour Only approved USDT purchases count toward BV
 * without enabling ROI or level commission.
 */
class PackageBusinessVolumeEligibility
{
    /**
     * Commerce package types that contribute approved purchase amounts to business volume.
     *
     * @var list<string>
     */
    public const BUSINESS_VOLUME_PACKAGE_TYPES = [
        Package::TYPE_TOUR,
        Package::TYPE_TOUR_ROI,
        Package::TYPE_PREMIUM,
        Package::TYPE_ROI,
    ];

    /**
     * Apply BV-only filters onto a package_purchases query builder.
     *
     * Counts each approved USDT purchase snapshot once (price_usd). Excludes
     * pending/rejected, VERIFY-1, local-test payments/emails. Does not read catalog price.
     */
    public function applyPurchaseConstraints(Builder $query): Builder
    {
        return $query
            ->where('status', PackagePurchase::APPROVED)
            ->whereNotNull('reviewed_at')
            ->where('denomination', 'USDT')
            ->where('package_code', '!=', 'VERIFY-1')
            ->whereIn('package_type', self::BUSINESS_VOLUME_PACKAGE_TYPES)
            ->where(function ($reference): void {
                $reference->whereNull('payment_reference')
                    ->orWhere('payment_reference', 'not like', LocalTestMarker::PAYMENT_PREFIX.'%');
            })
            ->whereNotExists(function ($members): void {
                $members->selectRaw('1')
                    ->from('members')
                    ->whereColumn('members.member_id', 'package_purchases.member_id')
                    ->where('members.email', 'like', '%'.LocalTestMarker::EMAIL_DOMAIN);
            });
    }
}
