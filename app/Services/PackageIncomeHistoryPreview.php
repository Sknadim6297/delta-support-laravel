<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use Carbon\CarbonImmutable;

/**
 * Read-only window for approved and unapproved purchases.
 * Amounts labeled as potential are not ledger credits and are not written.
 */
class PackageIncomeHistoryPreview
{
    public function __construct(
        private readonly PackageIncomePolicy $policy,
        private readonly ReferralLevelEligibility $eligibility,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(?string $asOf = null): array
    {
        $date = CarbonImmutable::parse(
            $asOf ?: CarbonImmutable::now(config('deltasupport.package_income.timezone'))->toDateString(),
            config('deltasupport.package_income.timezone')
        )->startOfDay();

        $purchases = PackagePurchase::query()->orderBy('id')->get();
        $packages = Package::query()->whereIn('id', $purchases->pluck('package_id')->filter()->all())->get()->keyBy('id');
        $rows = [];
        foreach ($purchases as $purchase) {
            $rows[] = $this->row($purchase, $packages->get($purchase->package_id), $date);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(PackagePurchase $purchase, ?Package $package, CarbonImmutable $asOf): array
    {
        $definition = $this->policy->definition($purchase->package_code);
        $first = $this->policy->firstAccrualDate($purchase);
        $roiDays = $definition['roi_days'] ?? 0;
        $commissionDays = $definition['commission_days'] ?? null;
        $roiEligible = $this->policy->roiEligible($purchase, $package);
        $levelEligible = $this->policy->levelSourceEligible($purchase, $package);
        $lastRoi = ($roiEligible && $first) ? $this->policy->lastAccrualDate($first, $roiDays) : null;
        $lastCommission = ($levelEligible && $first && $commissionDays) ? $this->policy->lastAccrualDate($first, (int) $commissionDays) : null;
        $roiElapsed = ($roiEligible && $first && $lastRoi) ? $this->elapsed($asOf, $first, $lastRoi) : 0;
        $commissionElapsed = ($levelEligible && $first && $lastCommission) ? $this->elapsed($asOf, $first, $lastCommission) : 0;
        $roiCreditedDays = PackageRoiAccrual::query()->where('package_purchase_id', $purchase->id)->count();
        $commissionCreditedDays = (int) PackageLevelAccrual::query()
            ->where('package_purchase_id', $purchase->id)
            ->distinct()
            ->count('business_date');
        $roiMissing = max(0, $roiElapsed - $roiCreditedDays);
        $commissionMissing = max(0, $commissionElapsed - $commissionCreditedDays);
        $roiDaily = $roiEligible ? $definition['roi_daily'] : '0.0000';
        $pool = $levelEligible ? $definition['commission_pool'] : '0.0000';
        $roiCredited = $this->money(PackageRoiAccrual::query()->where('package_purchase_id', $purchase->id)->sum('amount'));
        $levelCredited = $this->money(PackageLevelAccrual::query()->where('package_purchase_id', $purchase->id)->sum('amount'));

        return [
            'member_id' => $purchase->member_id,
            'package_name' => $purchase->package_name,
            'package_code' => $purchase->package_code,
            'purchase_price' => bcadd((string) $purchase->price_usd, '0', 4),
            'currency' => (string) ($purchase->denomination ?: ''),
            'approval_date' => $this->policy->approvalDate($purchase)?->toDateString(),
            'first_income_date' => ($roiEligible || $levelEligible) ? $first?->toDateString() : null,
            'last_roi_date' => $lastRoi?->toDateString(),
            'last_commission_date' => $lastCommission?->toDateString(),
            'roi_elapsed_days' => $roiElapsed,
            'roi_credited_days' => $roiCreditedDays,
            'roi_missing_days' => $roiMissing,
            'roi_daily' => $roiDaily,
            'roi_potential_amount' => bcmul($roiDaily, (string) $roiElapsed, 4),
            'roi_credited_amount' => $roiCredited,
            'roi_missing_amount' => bcmul($roiDaily, (string) $roiMissing, 4),
            'commission_elapsed_days' => $commissionElapsed,
            'commission_credited_days' => $commissionCreditedDays,
            'commission_missing_days' => $commissionMissing,
            'commission_pool' => $pool,
            'commission_potential_pool' => bcmul($pool, (string) $commissionElapsed, 4),
            'level_credited_amount' => $levelCredited,
            'blockers' => $this->blockers($purchase, $package, $roiEligible, $levelEligible),
        ];
    }

    /**
     * @return list<string>
     */
    private function blockers(PackagePurchase $purchase, ?Package $package, bool $roiEligible, bool $levelEligible): array
    {
        $blockers = [];
        if ($purchase->status !== PackagePurchase::APPROVED) {
            $blockers[] = 'Purchase status is '.$purchase->status.'.';
        }
        if ($purchase->reviewed_at === null) {
            $blockers[] = 'Approval date is not stored.';
        }
        if (! $roiEligible) {
            $blockers[] = 'Not eligible for ROI.';
        }
        if (! $levelEligible) {
            $blockers[] = 'Not eligible for level commission.';
        }
        if (! $roiEligible && ! $levelEligible) {
            return $blockers;
        }

        $source = Member::query()->where('member_id', $purchase->member_id)->first();
        if (! $source) {
            $blockers[] = 'Source member record is missing.';

            return $blockers;
        }

        $cursor = $source->sponsor_id;
        if (! filled($cursor)) {
            $blockers[] = 'Sponsor is missing.';

            return $blockers;
        }
        if ($cursor === $this->policy->adminRootId()) {
            $blockers[] = 'Admin root receives no level commission.';

            return $blockers;
        }
        $sponsor = Member::query()->where('member_id', $cursor)->first();
        if (! $sponsor) {
            $blockers[] = 'Sponsor '.$cursor.' has no member record.';

            return $blockers;
        }
        $unlocked = $this->eligibility->forMember($sponsor->member_id)['unlocked_levels'];
        $eligible = Member::query()
            ->where('member_id', $sponsor->member_id)
            ->whereExists(function ($query): void {
                $this->eligibility->qualifyingPurchaseConstraint($query);
            })
            ->exists();
        if (! $eligible) {
            $blockers[] = 'Sponsor '.$sponsor->member_id.' has no approved level-income package.';
        }
        if ($unlocked < 1) {
            $blockers[] = 'Sponsor '.$sponsor->member_id.' has 0 unlocked levels.';
        }

        return $blockers;
    }

    private function elapsed(CarbonImmutable $asOf, CarbonImmutable $first, CarbonImmutable $last): int
    {
        if ($asOf->lessThan($first)) {
            return 0;
        }
        $end = $asOf->lessThan($last) ? $asOf : $last;

        return (int) $first->diffInDays($end) + 1;
    }

    private function money(mixed $amount): string
    {
        return bcadd((string) ($amount ?: '0'), '0', 4);
    }
}
