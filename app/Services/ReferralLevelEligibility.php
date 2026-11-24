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
     * Distinguishes registered directs vs income-qualifying directs (approved
     * income-eligible packages). Registration alone does not unlock levels unless
     * a posting-authorized band policy is explicitly changed by Admin.
     *
     * @return array{
     *   registered_direct_count: int,
     *   qualifying_direct_count: int,
     *   unlocked_levels: int,
     *   legacy_unlocked_levels: int,
     *   authorized_preview_unlocked_levels: int|null,
     *   personally_activated: bool,
     *   next_unlock: ?array{required_directs: int, target_levels: int, message: string},
     *   rule_mode: string,
     *   applicable_note: string
     * }
     */
    public function forMember(string $memberId, ?PackagePurchase $sourcePurchase = null, ?string $businessDate = null): array
    {
        $qualification = app(LevelIncomeDirectQualification::class);
        $registered = $this->directCount($memberId);
        $incomeEligibleCount = $this->qualifyingDirectCount($memberId);
        $legacyUnlocked = $this->resolver->legacyLevelsForCount($incomeEligibleCount);

        $authorizedVersion = $this->resolver->authorizedVersion();
        $usePurchaseContext = $sourcePurchase !== null && $businessDate !== null;
        $mode = LevelIncomeRuleResolver::MODE_LEGACY;
        if ($usePurchaseContext) {
            $mode = $this->resolver->usesAuthorizedConfiguration($sourcePurchase, $businessDate)
                ? LevelIncomeRuleResolver::MODE_AUTHORIZED
                : LevelIncomeRuleResolver::MODE_LEGACY;
        } elseif ($authorizedVersion !== null) {
            $mode = LevelIncomeRuleResolver::MODE_AUTHORIZED;
        }

        if ($mode === LevelIncomeRuleResolver::MODE_AUTHORIZED && $authorizedVersion !== null) {
            $count = $qualification->countForVersion($memberId, $authorizedVersion);
            $activated = $qualification->isPersonallyActivatedForVersion($memberId, $authorizedVersion);
            $unlocked = $usePurchaseContext
                ? $this->resolver->unlockedLevelsForPurchase($sourcePurchase, $businessDate, $count, $activated)
                : $this->resolver->unlockedLevels($count, $activated);
            $note = 'Posting-authorized Admin bands and qualification settings apply (basis: '
                .$qualification->normalizeBasis($authorizedVersion->direct_qualification_basis)
                .'; tour_only_unlock='
                .(($authorizedVersion->tour_only_qualifies_for_unlock ?? false) ? 'yes' : 'no')
                .').';
        } else {
            $count = $incomeEligibleCount;
            $activated = $this->resolver->isPersonallyActivated($memberId);
            $unlocked = $usePurchaseContext
                ? $this->resolver->unlockedLevelsForPurchase($sourcePurchase, $businessDate, $count, $activated)
                : $this->resolver->unlockedLevels($count, $activated);
            $note = 'Live posting still uses the legacy unlock ladder and income-eligible unique directs until Admin publishes and authorizes a new version. Tour Only alone does not unlock under live rules.';
        }

        $authorizedPreview = null;
        $qualificationPreview = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('level_income_rule_versions')) {
            $previewVersion = \App\Models\LevelIncomeRuleVersion::query()
                ->with('bands')
                ->where('version_code', 'proposed_activation_unlock_v1')
                ->first();
            if ($previewVersion !== null && $previewVersion->bands->isNotEmpty()) {
                $previewCount = $qualification->countForVersion($memberId, $previewVersion);
                $previewActivated = $qualification->isPersonallyActivatedForVersion($memberId, $previewVersion);
                $authorizedPreview = $this->resolver->simulateUnlockedLevels(
                    $previewVersion->bands->map(fn ($b): array => [
                        'start_level' => (int) $b->start_level,
                        'end_level' => (int) $b->end_level,
                        'min_qualifying_directs' => (int) $b->min_qualifying_directs,
                        'requires_personal_activation' => (bool) $b->requires_personal_activation,
                    ])->all(),
                    $previewCount,
                    $previewActivated
                );
                $qualificationPreview = $qualification->memberPreview($memberId, $previewVersion);
            }
        }

        return [
            'registered_direct_count' => $registered,
            'qualifying_direct_count' => $count,
            'income_eligible_direct_count' => $incomeEligibleCount,
            'unlocked_levels' => $unlocked,
            'legacy_unlocked_levels' => $legacyUnlocked,
            'authorized_preview_unlocked_levels' => $authorizedPreview,
            'personally_activated' => $activated,
            'next_unlock' => $this->nextUnlockRequirement($count, $activated, $unlocked),
            'rule_mode' => $mode,
            'applicable_note' => $note,
            'qualification_preview' => $qualificationPreview,
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
     *   registered_direct_count: int,
     *   qualifying_direct_count: int,
     *   unlocked_levels: int,
     *   legacy_unlocked_levels: int,
     *   personally_activated: bool,
     *   rule_mode: string,
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
        $authorized = $this->resolver->authorizedVersion();
        $mode = $authorized !== null
            ? LevelIncomeRuleResolver::MODE_AUTHORIZED
            : LevelIncomeRuleResolver::MODE_LEGACY;
        $qualification = $authorized !== null ? app(LevelIncomeDirectQualification::class) : null;

        $summaries = [];
        foreach ($memberIds as $memberId) {
            $registered = (int) ($directCounts[$memberId] ?? 0);
            $incomeEligible = (int) ($qualifyingCounts[$memberId] ?? 0);
            if ($qualification !== null && $authorized !== null) {
                $qualifying = $qualification->countForVersion($memberId, $authorized);
                $activated = $qualification->isPersonallyActivatedForVersion($memberId, $authorized);
            } else {
                $qualifying = $incomeEligible;
                $activated = isset($activatedLookup[$memberId]);
            }
            $unlocked = $this->resolver->unlockedLevels($qualifying, $activated);
            $summaries[$memberId] = [
                'direct_count' => $registered,
                'registered_direct_count' => $registered,
                'qualifying_direct_count' => $qualifying,
                'income_eligible_direct_count' => $incomeEligible,
                'unlocked_levels' => $unlocked,
                'legacy_unlocked_levels' => $this->resolver->legacyLevelsForCount($incomeEligible),
                'personally_activated' => $activated,
                'rule_mode' => $mode,
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
     * Unlock ladder for a qualifying-direct count.
     * When posting is unauthorized, matches live legacy financial behavior.
     * When authorized, requires explicit personal-activation context (default false).
     */
    public function levelsForCount(int $qualifyingDirectCount, bool $personallyActivated = false): int
    {
        if ($this->resolver->authorizedVersion() === null) {
            return $this->resolver->legacyLevelsForCount($qualifyingDirectCount);
        }

        return $this->resolver->unlockedLevels($qualifyingDirectCount, $personallyActivated);
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
