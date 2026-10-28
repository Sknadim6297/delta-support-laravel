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
    ];

    /**
     * @return array{qualifying_direct_count: int, unlocked_levels: int}
     */
    public function forMember(string $memberId): array
    {
        $count = $this->qualifyingDirectCount($memberId);

        return [
            'qualifying_direct_count' => $count,
            'unlocked_levels' => $this->levelsForCount($count),
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
     * @return array<string, array{direct_count: int, qualifying_direct_count: int, unlocked_levels: int, status: string}>
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

        $summaries = [];
        foreach ($memberIds as $memberId) {
            $qualifying = (int) ($qualifyingCounts[$memberId] ?? 0);
            $unlocked = $this->levelsForCount($qualifying);
            $summaries[$memberId] = [
                'direct_count' => (int) ($directCounts[$memberId] ?? 0),
                'qualifying_direct_count' => $qualifying,
                'unlocked_levels' => $unlocked,
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

    public function levelsForCount(int $qualifyingDirectCount): int
    {
        return match (true) {
            $qualifyingDirectCount >= 5 => self::MAX_LEVELS,
            $qualifyingDirectCount === 4 => 16,
            $qualifyingDirectCount === 3 => 12,
            $qualifyingDirectCount === 2 => 8,
            $qualifyingDirectCount === 1 => 4,
            default => 0,
        };
    }
}
