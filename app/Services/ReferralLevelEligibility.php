<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;

class ReferralLevelEligibility
{
    public const MAX_LEVELS = 20;

    /**
     * Purchase types that reserve future income. A tour-only purchase does not qualify.
     *
     * @var list<string>
     */
    public const QUALIFYING_PACKAGE_TYPES = [
        Package::TYPE_TOUR_ROI,
        Package::TYPE_PREMIUM,
        Package::TYPE_ROI,
    ];

    public function __construct(private readonly LevelIncomeRuleResolver $resolver)
    {
    }

    /**
     * Member unlock summary. When $sourcePurchase + $businessDate are provided,
     * unlock uses purchase-scoped applicability (legacy vs authorized).
     *
     * @return array{
     *   qualifying_direct_count: int,
     *   unlocked_levels: int,
     *   personally_activated: bool,
     *   next_unlock: ?array{required_directs: int, target_levels: int, message: string},
     *   rule_mode: string
     * }
     */
    public function forMember(string $memberId, ?PackagePurchase $sourcePurchase = null, ?string $businessDate = null): array
    {
        $count = $this->qualifyingDirectCount($memberId);
        $activated = $this->resolver->isPersonallyActivated($memberId);

        if ($sourcePurchase !== null && $businessDate !== null) {
            $unlocked = $this->resolver->unlockedLevelsForPurchase($sourcePurchase, $businessDate, $count, $activated);
            $mode = $this->resolver->usesAuthorizedConfiguration($sourcePurchase, $businessDate)
                ? LevelIncomeRuleResolver::MODE_AUTHORIZED
                : LevelIncomeRuleResolver::MODE_LEGACY;
        } else {
            $unlocked = $this->resolver->unlockedLevels($count, $activated);
            $mode = $this->resolver->authorizedVersion() !== null
                ? LevelIncomeRuleResolver::MODE_AUTHORIZED
                : LevelIncomeRuleResolver::MODE_LEGACY;
        }

        return [
            'qualifying_direct_count' => $count,
            'unlocked_levels' => $unlocked,
            'personally_activated' => $activated,
            'next_unlock' => $this->nextUnlockRequirement($count, $activated, $unlocked),
            'rule_mode' => $mode,
        ];
    }

    public function qualifyingDirectCount(string $memberId): int
    {
        return Member::query()
            ->where('sponsor_id', $memberId)
            ->where('member_id', '!=', $memberId)
            ->whereExists(function ($query): void {
                $this->qualifyingPurchaseConstraint($query);
            })
            ->count();
    }

    /**
     * @param  list<string>  $memberIds
     * @return list<string>
     */
    public function qualifyingIdsAmong(array $memberIds): array
    {
        if ($memberIds === []) {
            return [];
        }

        return Member::query()
            ->whereIn('member_id', $memberIds)
            ->whereExists(function ($query): void {
                $this->qualifyingPurchaseConstraint($query);
            })
            ->pluck('member_id')
            ->all();
    }

    public function directCount(string $memberId): int
    {
        return Member::query()
            ->where('sponsor_id', $memberId)
            ->where('member_id', '!=', $memberId)
            ->count();
    }

    /**
     * Two grouped counts for a page of members. This does not walk the full genealogy tree.
     *
     * @param  list<string>  $memberIds
     * @return array<string, array{
     *   direct_count: int,
     *   qualifying_direct_count: int,
     *   unlocked_levels: int,
     *   personally_activated: bool,
     *   status: string
     * }>
     */
    public function summariesForMemberIds(array $memberIds): array
    {
        $memberIds = array_values(array_unique(array_filter($memberIds)));
        if ($memberIds === []) {
            return [];
        }

        $directCounts = Member::query()
            ->whereIn('sponsor_id', $memberIds)
            ->whereColumn('member_id', '!=', 'sponsor_id')
            ->selectRaw('sponsor_id, COUNT(*) as aggregate')
            ->groupBy('sponsor_id')
            ->pluck('aggregate', 'sponsor_id');

        $qualifyingCounts = Member::query()
            ->whereIn('sponsor_id', $memberIds)
            ->whereColumn('member_id', '!=', 'sponsor_id')
            ->whereExists(function ($query): void {
                $this->qualifyingPurchaseConstraint($query);
            })
            ->selectRaw('sponsor_id, COUNT(*) as aggregate')
            ->groupBy('sponsor_id')
            ->pluck('aggregate', 'sponsor_id');

        $activatedIds = Member::query()
            ->whereIn('member_id', $memberIds)
            ->whereExists(function ($query): void {
                $this->qualifyingPurchaseConstraint($query);
            })
            ->pluck('member_id')
            ->all();
        $activatedLookup = array_fill_keys($activatedIds, true);

        $summaries = [];
        foreach ($memberIds as $memberId) {
            $qualifying = (int) ($qualifyingCounts[$memberId] ?? 0);
            $activated = isset($activatedLookup[$memberId]);
            $unlocked = $this->resolver->unlockedLevels($qualifying, $activated);
            $summaries[$memberId] = [
                'direct_count' => (int) ($directCounts[$memberId] ?? 0),
                'qualifying_direct_count' => $qualifying,
                'unlocked_levels' => $unlocked,
                'personally_activated' => $activated,
                'status' => $this->statusLabel($unlocked),
            ];
        }

        return $summaries;
    }

    public function statusLabel(int $unlockedLevels): string
    {
        if ($unlockedLevels <= 0) {
            return 'No levels unlocked';
        }

        if ($unlockedLevels >= self::MAX_LEVELS) {
            return 'All 20 levels unlocked';
        }

        return $unlockedLevels.' of 20 levels unlocked';
    }

    public function qualifyingPurchaseConstraint($query): void
    {
        $codes = array_keys(config('deltasupport.package_income.packages', []));
        $query->selectRaw('1')
            ->from('package_purchases')
            ->whereColumn('package_purchases.member_id', 'members.member_id')
            ->where('package_purchases.status', PackagePurchase::APPROVED)
            ->where(function ($purchase) use ($codes): void {
                $purchase->whereIn('package_purchases.package_type', self::QUALIFYING_PACKAGE_TYPES);
                if ($codes !== []) {
                    $purchase->orWhere(function ($named) use ($codes): void {
                        $named->whereIn('package_purchases.package_code', $codes)
                            ->whereExists(function ($package): void {
                                $package->selectRaw('1')
                                    ->from('packages')
                                    ->whereColumn('packages.id', 'package_purchases.package_id')
                                    ->where('packages.level_commission_eligible', true);
                            });
                    });
                }
            });
    }

    /**
     * Legacy ladder helper. When posting is unauthorized, matches live financial behavior.
     * Prefer forMember() when personal activation matters under authorized configs.
     */
    public function levelsForCount(int $qualifyingDirectCount): int
    {
        // Keep live posting behavior stable: until a version is posting_authorized,
        // always return the hard-coded legacy ladder (ignore draft/proposed bands).
        if ($this->resolver->authorizedVersion() === null) {
            return $this->resolver->legacyLevelsForCount($qualifyingDirectCount);
        }

        // Authorized configs need activation context; without it, treat as inactive.
        return $this->resolver->unlockedLevels($qualifyingDirectCount, true);
    }

    /**
     * @return ?array{required_directs: int, target_levels: int, message: string}
     */
    public function nextUnlockRequirement(int $qualifyingDirectCount, bool $personallyActivated, int $unlockedLevels): ?array
    {
        if ($unlockedLevels >= self::MAX_LEVELS) {
            return null;
        }

        if (! $personallyActivated) {
            return [
                'required_directs' => $qualifyingDirectCount,
                'target_levels' => max(1, $unlockedLevels),
                'message' => 'Personal activation via a qualifying approved package is required before Level Income unlocks.',
            ];
        }

        for ($directs = $qualifyingDirectCount + 1; $directs <= 20; $directs++) {
            $next = $this->resolver->unlockedLevels($directs, true);
            if ($next > $unlockedLevels) {
                $needed = $directs - $qualifyingDirectCount;

                return [
                    'required_directs' => $directs,
                    'target_levels' => $next,
                    'message' => $needed === 1
                        ? "1 more qualifying direct unlocks levels 1–{$next}."
                        : "{$needed} more qualifying directs unlock levels 1–{$next}.",
                ];
            }
        }

        return null;
    }
}
