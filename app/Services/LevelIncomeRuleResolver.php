<?php

namespace App\Services;

use App\Models\LevelIncomeRuleVersion;
use App\Models\Member;
use App\Models\PackagePurchase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves unlock bands and commission rates for Level Income.
 *
 * Financial posting uses DB configuration ONLY when a published version has
 * posting_authorized=true AND the source purchase / business date falls under
 * that version's applicability policy. Otherwise hard-coded legacy defaults apply.
 */
class LevelIncomeRuleResolver
{
    public const MODE_LEGACY = 'legacy';

    public const MODE_AUTHORIZED = 'authorized';

    private ?LevelIncomeRuleVersion $authorizedCache = null;

    private bool $resolvedAuthorized = false;

    /**
     * Display / global chart: authorized version rates when posting is authorized,
     * otherwise hard-coded legacy. Accrual must use rateChartForPurchase().
     *
     * @return array<int, string>
     */
    public function rateChart(): array
    {
        return $this->rateChartUsingAuthorized($this->authorizedVersion() !== null);
    }

    public function rateForLevel(int $level): ?string
    {
        $chart = $this->rateChart();

        return $chart[$level] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function rateChartForPurchase(PackagePurchase $purchase, string $businessDate): array
    {
        return $this->rateChartUsingAuthorized($this->usesAuthorizedConfiguration($purchase, $businessDate));
    }

    public function rateForLevelForPurchase(PackagePurchase $purchase, string $businessDate, int $level): ?string
    {
        $chart = $this->rateChartForPurchase($purchase, $businessDate);

        return $chart[$level] ?? null;
    }

    /**
     * Display unlock without purchase context. Accrual must use unlockedLevelsForPurchase().
     */
    public function unlockedLevels(int $qualifyingDirectCount, bool $personallyActivated): int
    {
        return $this->unlockedLevelsUsingAuthorized(
            $qualifyingDirectCount,
            $personallyActivated,
            $this->authorizedVersion() !== null
        );
    }

    public function unlockedLevelsForPurchase(
        PackagePurchase $purchase,
        string $businessDate,
        int $qualifyingDirectCount,
        bool $personallyActivated
    ): int {
        return $this->unlockedLevelsUsingAuthorized(
            $qualifyingDirectCount,
            $personallyActivated,
            $this->usesAuthorizedConfiguration($purchase, $businessDate)
        );
    }

    /**
     * Decide whether the authorized published version applies to this source purchase
     * on the given business date. Defaults to legacy when anything is incomplete.
     */
    public function usesAuthorizedConfiguration(PackagePurchase $purchase, string $businessDate): bool
    {
        return $this->postingContextFor($purchase, $businessDate)['mode'] === self::MODE_AUTHORIZED;
    }

    /**
     * @return array{
     *   mode: string,
     *   version: ?LevelIncomeRuleVersion,
     *   applicability: ?string,
     *   effective_from: ?string,
     *   purchase_approval_date: ?string,
     *   business_date: string,
     *   reason: string
     * }
     */
    public function postingContextFor(PackagePurchase $purchase, string $businessDate): array
    {
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $business = CarbonImmutable::parse($businessDate, $timezone)->toDateString();
        $authorized = $this->authorizedVersion();

        $base = [
            'mode' => self::MODE_LEGACY,
            'version' => $authorized,
            'applicability' => $authorized?->applicability,
            'effective_from' => optional($authorized?->effective_from)?->toDateString(),
            'purchase_approval_date' => $this->purchaseApprovalDate($purchase),
            'business_date' => $business,
            'reason' => 'Hard-coded legacy Level Income rules.',
        ];

        if ($authorized === null) {
            $base['reason'] = 'No published Level Income version is posting-authorized.';

            return $base;
        }

        $applicability = (string) $authorized->applicability;
        if (! in_array($applicability, [
            LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
        ], true)) {
            $base['reason'] = 'Authorized version has missing or invalid applicability; legacy rules apply.';

            return $base;
        }

        $effective = optional($authorized->effective_from)?->toDateString();
        if ($effective === null) {
            $base['reason'] = 'Authorized version has no effective date; legacy rules apply.';

            return $base;
        }

        if ($applicability === LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY) {
            $approval = $this->purchaseApprovalDate($purchase);
            if ($approval === null) {
                $base['reason'] = 'Purchase has no approval timestamp; legacy rules apply.';

                return $base;
            }
            if ($approval < $effective) {
                $base['reason'] = 'Purchase approved before effective date; legacy rules apply (future_purchases_only).';

                return $base;
            }

            return array_merge($base, [
                'mode' => self::MODE_AUTHORIZED,
                'reason' => 'Purchase approved on/after effective date; authorized version applies (future_purchases_only).',
            ]);
        }

        // existing_prospective: new rules from effective business date onward; never backfill prior dates.
        if ($business < $effective) {
            $base['reason'] = 'Business date is before effective date; legacy rules apply (existing_prospective).';

            return $base;
        }

        return array_merge($base, [
            'mode' => self::MODE_AUTHORIZED,
            'reason' => 'Business date on/after effective date; authorized version applies (existing_prospective).',
        ]);
    }

    /**
     * Legacy hard-coded ladder preserved for posting until authorization / applicability match.
     */
    public function legacyLevelsForCount(int $qualifyingDirectCount): int
    {
        return match (true) {
            $qualifyingDirectCount >= 5 => ReferralLevelEligibility::MAX_LEVELS,
            $qualifyingDirectCount === 4 => 16,
            $qualifyingDirectCount === 3 => 12,
            $qualifyingDirectCount === 2 => 8,
            $qualifyingDirectCount === 1 => 4,
            default => 0,
        };
    }

    public function isPersonallyActivated(string $memberId): bool
    {
        return Member::query()
            ->where('member_id', $memberId)
            ->whereExists(function ($query): void {
                app(ReferralLevelEligibility::class)->qualifyingPurchaseConstraint($query);
            })
            ->exists();
    }

    public function authorizedVersion(): ?LevelIncomeRuleVersion
    {
        if ($this->resolvedAuthorized) {
            return $this->authorizedCache;
        }

        $this->resolvedAuthorized = true;
        if (! Schema::hasTable('level_income_rule_versions')) {
            $this->authorizedCache = null;

            return null;
        }

        $this->authorizedCache = LevelIncomeRuleVersion::query()
            ->with(['bands', 'rates'])
            ->where('status', LevelIncomeRuleVersion::STATUS_PUBLISHED)
            ->where('posting_authorized', true)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();

        return $this->authorizedCache;
    }

    public function publishedBaseline(): ?LevelIncomeRuleVersion
    {
        if (! Schema::hasTable('level_income_rule_versions')) {
            return null;
        }

        return LevelIncomeRuleVersion::query()
            ->with(['bands', 'rates'])
            ->where('status', LevelIncomeRuleVersion::STATUS_PUBLISHED)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Simulate unlock for a version without requiring posting authorization.
     *
     * @param  list<array{start_level:int,end_level:int,min_qualifying_directs:int,requires_personal_activation:bool}>  $bands
     */
    public function simulateUnlockedLevels(array $bands, int $qualifyingDirectCount, bool $personallyActivated): int
    {
        $unlocked = 0;
        foreach ($bands as $band) {
            if (! empty($band['requires_personal_activation']) && ! $personallyActivated) {
                continue;
            }
            if ($qualifyingDirectCount < (int) $band['min_qualifying_directs']) {
                continue;
            }
            $unlocked = max($unlocked, (int) $band['end_level']);
        }

        return min($unlocked, ReferralLevelEligibility::MAX_LEVELS);
    }

    public function clearCache(): void
    {
        $this->resolvedAuthorized = false;
        $this->authorizedCache = null;
    }

    /**
     * @return array<int, string>
     */
    private function rateChartUsingAuthorized(bool $useAuthorized): array
    {
        if (! $useAuthorized) {
            return CommissionPlan::RATE_BY_LEVEL;
        }

        $authorized = $this->authorizedVersion();
        if ($authorized === null) {
            return CommissionPlan::RATE_BY_LEVEL;
        }

        $rates = [];
        foreach ($authorized->rates as $row) {
            $rates[(int) $row->level] = bcadd((string) $row->percentage, '0', 4);
        }

        return $rates !== [] ? $rates : CommissionPlan::RATE_BY_LEVEL;
    }

    private function unlockedLevelsUsingAuthorized(
        int $qualifyingDirectCount,
        bool $personallyActivated,
        bool $useAuthorized
    ): int {
        if (! $useAuthorized) {
            return $this->legacyLevelsForCount($qualifyingDirectCount);
        }

        $authorized = $this->authorizedVersion();
        if ($authorized === null) {
            return $this->legacyLevelsForCount($qualifyingDirectCount);
        }

        $unlocked = 0;
        foreach ($authorized->bands as $band) {
            if ($band->requires_personal_activation && ! $personallyActivated) {
                continue;
            }
            if ($qualifyingDirectCount < (int) $band->min_qualifying_directs) {
                continue;
            }
            $unlocked = max($unlocked, (int) $band->end_level);
        }

        return min($unlocked, ReferralLevelEligibility::MAX_LEVELS);
    }

    private function purchaseApprovalDate(PackagePurchase $purchase): ?string
    {
        if ($purchase->reviewed_at === null) {
            return null;
        }

        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');

        return CarbonImmutable::parse($purchase->reviewed_at)
            ->timezone($timezone)
            ->toDateString();
    }
}
