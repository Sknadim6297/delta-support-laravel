<?php

namespace App\Services;

use App\Models\LevelIncomeRuleVersion;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use Illuminate\Support\Facades\Schema;

/**
 * Counts directs / purchases for Level Unlock under a version's qualification settings.
 * Separate from PackageBusinessVolumeEligibility (Total Business) and from ROI/commission generation.
 */
class LevelIncomeDirectQualification
{
    public const BASIS_REGISTERED_DIRECTS = 'registered_directs';

    public const BASIS_APPROVED_PACKAGE_DIRECTS = 'approved_package_directs';

    public const BASIS_INCOME_ELIGIBLE_DIRECTS = 'income_eligible_directs';

    public const BASIS_APPROVED_PACKAGE_PURCHASE_COUNT = 'approved_package_purchase_count';

    public const BASES = [
        self::BASIS_REGISTERED_DIRECTS,
        self::BASIS_APPROVED_PACKAGE_DIRECTS,
        self::BASIS_INCOME_ELIGIBLE_DIRECTS,
        self::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT,
    ];

    public static function labels(): array
    {
        return [
            self::BASIS_REGISTERED_DIRECTS => 'Registered direct members (unique)',
            self::BASIS_APPROVED_PACKAGE_DIRECTS => 'Direct members with approved packages (unique)',
            self::BASIS_INCOME_ELIGIBLE_DIRECTS => 'Direct members with income-eligible approved packages (unique)',
            self::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT => 'Approved direct package purchase count (not unique members)',
        ];
    }

    private function eligibility(): ReferralLevelEligibility
    {
        return app(ReferralLevelEligibility::class);
    }

    public function normalizeBasis(?string $basis): string
    {
        $basis = (string) $basis;
        if (! in_array($basis, self::BASES, true)) {
            return self::BASIS_INCOME_ELIGIBLE_DIRECTS;
        }

        return $basis;
    }

    /**
     * Live posting count: authorized version settings, else legacy income-eligible unique directs.
     */
    public function liveDirectMetric(string $memberId): int
    {
        $authorized = app(LevelIncomeRuleResolver::class)->authorizedVersion();
        if ($authorized === null) {
            return $this->eligibility()->qualifyingDirectCount($memberId);
        }

        return $this->countForVersion($memberId, $authorized);
    }

    public function countForVersion(string $memberId, LevelIncomeRuleVersion $version): int
    {
        $basis = $this->normalizeBasis($version->direct_qualification_basis ?? null);
        $tourOnly = (bool) ($version->tour_only_qualifies_for_unlock ?? false);

        return match ($basis) {
            self::BASIS_REGISTERED_DIRECTS => $this->eligibility()->directCount($memberId),
            self::BASIS_APPROVED_PACKAGE_DIRECTS => $this->uniqueDirectsWithApprovedPackages($memberId, $tourOnly, false),
            self::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT => $this->approvedPurchaseCountByDirects($memberId, $tourOnly, false),
            default => $this->uniqueDirectsWithApprovedPackages($memberId, $tourOnly, true),
        };
    }

    public function isPersonallyActivatedForVersion(string $memberId, ?LevelIncomeRuleVersion $version): bool
    {
        if ($version === null) {
            return app(LevelIncomeRuleResolver::class)->isPersonallyActivated($memberId);
        }

        $basis = $this->normalizeBasis($version->direct_qualification_basis ?? null);
        $tourOnly = (bool) ($version->tour_only_qualifies_for_unlock ?? false);
        // Personal activation follows the version's package filter, not the direct-count basis.
        $incomeEligibleOnly = $basis === self::BASIS_INCOME_ELIGIBLE_DIRECTS;

        return Member::query()
            ->where('member_id', $memberId)
            ->whereExists(function ($query) use ($tourOnly, $incomeEligibleOnly): void {
                $this->approvedPurchaseExistsConstraint($query, $tourOnly, $incomeEligibleOnly);
            })
            ->exists();
    }

    /**
     * Read-only per-member comparison across qualification modes.
     *
     * @return array<string, mixed>
     */
    public function memberPreview(string $memberId, ?LevelIncomeRuleVersion $candidate = null): array
    {
        $resolver = app(LevelIncomeRuleResolver::class);
        $authorized = $resolver->authorizedVersion();
        $candidate ??= LevelIncomeRuleVersion::query()
            ->with('bands')
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->first();

        $registered = $this->eligibility()->directCount($memberId);
        $incomeEligibleDirects = $this->eligibility()->qualifyingDirectCount($memberId);
        $approvedPackageDirects = $this->uniqueDirectsWithApprovedPackages($memberId, true, false);
        $approvedPackageDirectsNoTour = $this->uniqueDirectsWithApprovedPackages($memberId, false, false);
        $tourOnlyDirects = $this->uniqueDirectsWithTourOnly($memberId);
        $purchaseCountInclTour = $this->approvedPurchaseCountByDirects($memberId, true, false);
        $purchaseCountIncomeOnly = $this->approvedPurchaseCountByDirects($memberId, false, true);
        $activatedLegacy = $resolver->isPersonallyActivated($memberId);

        $liveUnlocked = $resolver->unlockedLevels($incomeEligibleDirects, $activatedLegacy);

        $modeExpected = [];
        foreach (self::BASES as $basis) {
            foreach ([false, true] as $tourFlag) {
                $key = $basis.($tourFlag ? '+tour' : '');
                $count = match ($basis) {
                    self::BASIS_REGISTERED_DIRECTS => $registered,
                    self::BASIS_APPROVED_PACKAGE_DIRECTS => $tourFlag ? $approvedPackageDirects : $approvedPackageDirectsNoTour,
                    self::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT => $tourFlag
                        ? $purchaseCountInclTour
                        : $this->approvedPurchaseCountByDirects($memberId, false, false),
                    default => $tourFlag
                        ? $this->uniqueDirectsWithApprovedPackages($memberId, true, true)
                        : $incomeEligibleDirects,
                };
                $activated = $this->previewActivated($memberId, $basis, $tourFlag);
                $bands = $candidate?->bands;
                $expected = 0;
                if ($bands !== null && $bands->isNotEmpty()) {
                    $expected = $resolver->simulateUnlockedLevels(
                        $bands->map(fn ($b): array => [
                            'start_level' => (int) $b->start_level,
                            'end_level' => (int) $b->end_level,
                            'min_qualifying_directs' => (int) $b->min_qualifying_directs,
                            'requires_personal_activation' => (bool) $b->requires_personal_activation,
                        ])->all(),
                        $count,
                        $activated
                    );
                } else {
                    $expected = $resolver->legacyLevelsForCount($count);
                }
                $modeExpected[$key] = [
                    'basis' => $basis,
                    'tour_only_qualifies_for_unlock' => $tourFlag,
                    'direct_metric' => $count,
                    'personally_activated' => $activated,
                    'expected_unlocked_levels' => $expected,
                ];
            }
        }

        $candidateExpected = null;
        if ($candidate !== null) {
            $cCount = $this->countForVersion($memberId, $candidate);
            $cActivated = $this->isPersonallyActivatedForVersion($memberId, $candidate);
            $candidateExpected = [
                'version_code' => $candidate->version_code,
                'status' => $candidate->status,
                'posting_authorized' => (bool) $candidate->posting_authorized,
                'direct_qualification_basis' => $this->normalizeBasis($candidate->direct_qualification_basis ?? null),
                'tour_only_qualifies_for_unlock' => (bool) ($candidate->tour_only_qualifies_for_unlock ?? false),
                'direct_metric' => $cCount,
                'personally_activated' => $cActivated,
                'expected_unlocked_levels' => $resolver->simulateUnlockedLevels(
                    $candidate->bands->map(fn ($b): array => [
                        'start_level' => (int) $b->start_level,
                        'end_level' => (int) $b->end_level,
                        'min_qualifying_directs' => (int) $b->min_qualifying_directs,
                        'requires_personal_activation' => (bool) $b->requires_personal_activation,
                    ])->all(),
                    $cCount,
                    $cActivated
                ),
            ];
        }

        $reason = match (true) {
            ! $activatedLegacy && $liveUnlocked === 0 => 'No income-eligible personal activation under live legacy rules.',
            $incomeEligibleDirects === 0 && $liveUnlocked === 0 => 'Live legacy unlock needs ≥1 income-eligible qualifying direct (registration / Tour Only alone do not count).',
            default => $liveUnlocked > 0
                ? 'Live legacy unlock applied from income-eligible unique directs.'
                : 'Locked under live legacy ladder.',
        };

        return [
            'member_id' => $memberId,
            'registered_direct_count' => $registered,
            'income_eligible_direct_count' => $incomeEligibleDirects,
            'approved_package_direct_count' => $approvedPackageDirects,
            'approved_package_direct_count_excluding_tour_only' => $approvedPackageDirectsNoTour,
            'tour_only_purchasing_direct_count' => $tourOnlyDirects,
            'approved_direct_package_purchase_count' => $purchaseCountInclTour,
            'income_eligible_direct_package_purchase_count' => $purchaseCountIncomeOnly,
            'currently_unlocked_levels' => $liveUnlocked,
            'live_rule_mode' => $authorized ? LevelIncomeRuleResolver::MODE_AUTHORIZED : LevelIncomeRuleResolver::MODE_LEGACY,
            'applicable_version' => $authorized?->version_code ?? 'legacy_hardcoded',
            'locked_reason' => $reason,
            'candidate_expected' => $candidateExpected,
            'expected_under_each_mode' => $modeExpected,
            'note' => 'Previews do not authorize posting. Total Business (Tour Only BV) remains separate from unlock.',
        ];
    }

    private function previewActivated(string $memberId, string $basis, bool $tourOnly): bool
    {
        $stub = new LevelIncomeRuleVersion([
            'direct_qualification_basis' => $basis,
            'tour_only_qualifies_for_unlock' => $tourOnly,
        ]);

        return $this->isPersonallyActivatedForVersion($memberId, $stub);
    }

    private function uniqueDirectsWithApprovedPackages(string $memberId, bool $includeTourOnly, bool $incomeEligibleOnly): int
    {
        return Member::query()
            ->where('sponsor_id', $memberId)
            ->where('member_id', '!=', $memberId)
            ->whereExists(function ($query) use ($includeTourOnly, $incomeEligibleOnly): void {
                $this->approvedPurchaseExistsConstraint($query, $includeTourOnly, $incomeEligibleOnly);
            })
            ->count();
    }

    private function uniqueDirectsWithTourOnly(string $memberId): int
    {
        return Member::query()
            ->where('sponsor_id', $memberId)
            ->where('member_id', '!=', $memberId)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('package_purchases')
                    ->whereColumn('package_purchases.member_id', 'members.member_id')
                    ->where('package_purchases.status', PackagePurchase::APPROVED)
                    ->whereNotNull('package_purchases.reviewed_at')
                    ->where('package_purchases.package_type', Package::TYPE_TOUR);
            })
            ->count();
    }

    private function approvedPurchaseCountByDirects(string $memberId, bool $includeTourOnly, bool $incomeEligibleOnly): int
    {
        $directIds = Member::query()
            ->where('sponsor_id', $memberId)
            ->where('member_id', '!=', $memberId)
            ->pluck('member_id')
            ->all();
        if ($directIds === []) {
            return 0;
        }

        $query = PackagePurchase::query()
            ->whereIn('member_id', $directIds)
            ->where('status', PackagePurchase::APPROVED)
            ->whereNotNull('reviewed_at');

        $this->applyPackageTypeFilter($query, $includeTourOnly, $incomeEligibleOnly);

        return (int) $query->count();
    }

    private function approvedPurchaseExistsConstraint($query, bool $includeTourOnly, bool $incomeEligibleOnly): void
    {
        $query->selectRaw('1')
            ->from('package_purchases')
            ->whereColumn('package_purchases.member_id', 'members.member_id')
            ->where('package_purchases.status', PackagePurchase::APPROVED)
            ->whereNotNull('package_purchases.reviewed_at');

        $this->applyPackageTypeFilter($query, $includeTourOnly, $incomeEligibleOnly);
    }

    private function applyPackageTypeFilter($query, bool $includeTourOnly, bool $incomeEligibleOnly): void
    {
        // Eloquent PackagePurchase model uses bare columns; raw exists() uses table prefixes.
        $typeColumn = method_exists($query, 'getModel') ? 'package_type' : 'package_purchases.package_type';
        $codeColumn = method_exists($query, 'getModel') ? 'package_code' : 'package_purchases.package_code';
        $packageIdColumn = method_exists($query, 'getModel') ? 'package_id' : 'package_purchases.package_id';

        if ($incomeEligibleOnly) {
            $codes = array_keys(config('deltasupport.package_income.packages', []));
            $types = ReferralLevelEligibility::QUALIFYING_PACKAGE_TYPES;
            if ($includeTourOnly) {
                $types = array_values(array_unique(array_merge($types, [Package::TYPE_TOUR])));
            }
            $query->where(function ($purchase) use ($codes, $types, $typeColumn, $codeColumn, $packageIdColumn): void {
                $purchase->whereIn($typeColumn, $types);
                if ($codes !== []) {
                    $purchase->orWhere(function ($named) use ($codes, $codeColumn, $packageIdColumn): void {
                        $named->whereIn($codeColumn, $codes)
                            ->whereExists(function ($package) use ($packageIdColumn): void {
                                $package->selectRaw('1')
                                    ->from('packages')
                                    ->whereColumn('packages.id', $packageIdColumn)
                                    ->where('packages.level_commission_eligible', true);
                            });
                    });
                }
            });

            return;
        }

        $types = $includeTourOnly
            ? PackageBusinessVolumeEligibility::BUSINESS_VOLUME_PACKAGE_TYPES
            : array_values(array_filter(
                PackageBusinessVolumeEligibility::BUSINESS_VOLUME_PACKAGE_TYPES,
                fn (string $type): bool => $type !== Package::TYPE_TOUR
            ));
        $query->whereIn($typeColumn, $types);
    }
}
