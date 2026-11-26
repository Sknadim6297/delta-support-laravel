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

    /** Combined mode: joining and purchases contribute independently per version policy. */
    public const BASIS_COMBINED_JOINING_AND_PURCHASE = 'combined_joining_and_purchase';

    /**
     * @deprecated Use BASIS_COMBINED_JOINING_AND_PURCHASE (singular). Same stored value.
     */
    public const BASIS_COMBINED_JOINING_AND_PURCHASES = self::BASIS_COMBINED_JOINING_AND_PURCHASE;

    /** Purchase contribution counts the evaluated member's own approved purchases. */
    public const BASIS_PERSONAL_APPROVED_PURCHASES = 'personal_approved_purchases';

    public const PURCHASE_SCOPES = [
        self::BASIS_APPROVED_PACKAGE_DIRECTS,
        self::BASIS_INCOME_ELIGIBLE_DIRECTS,
        self::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT,
        self::BASIS_PERSONAL_APPROVED_PURCHASES,
    ];

    public const REPEAT_UNIQUE_DIRECTS = 'unique_directs';

    public const REPEAT_EACH_PURCHASE = 'each_approved_purchase';

    public const REPEAT_MODES = [
        self::REPEAT_UNIQUE_DIRECTS,
        self::REPEAT_EACH_PURCHASE,
    ];

    public const DOUBLE_ALLOW_SUM = 'allow_sum';

    public const DOUBLE_PREVENT_OVERLAP = 'prevent_overlap';

    public const DOUBLE_MAX_ONLY = 'max_only';

    public const DOUBLE_CREDIT_POLICIES = [
        self::DOUBLE_ALLOW_SUM,
        self::DOUBLE_PREVENT_OVERLAP,
        self::DOUBLE_MAX_ONLY,
    ];

    public const BASES = [
        self::BASIS_REGISTERED_DIRECTS,
        self::BASIS_APPROVED_PACKAGE_DIRECTS,
        self::BASIS_INCOME_ELIGIBLE_DIRECTS,
        self::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT,
        self::BASIS_COMBINED_JOINING_AND_PURCHASE,
    ];

    public static function labels(): array
    {
        return [
            self::BASIS_REGISTERED_DIRECTS => 'Registered direct members (unique)',
            self::BASIS_APPROVED_PACKAGE_DIRECTS => 'Direct members with approved packages (unique)',
            self::BASIS_INCOME_ELIGIBLE_DIRECTS => 'Direct members with income-eligible approved packages (unique)',
            self::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT => 'Approved direct package purchase count (not unique members)',
            self::BASIS_COMBINED_JOINING_AND_PURCHASE => 'Combined: direct joining + package purchases (configurable)',
        ];
    }

    public static function purchaseScopeLabels(): array
    {
        return [
            self::BASIS_APPROVED_PACKAGE_DIRECTS => 'Unique directs with approved packages',
            self::BASIS_INCOME_ELIGIBLE_DIRECTS => 'Unique directs with income-eligible approved packages',
            self::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT => 'Approved package purchase rows (see repeat mode)',
            self::BASIS_PERSONAL_APPROVED_PURCHASES => 'Personal (own) approved purchases of the evaluated member',
        ];
    }

    public static function repeatModeLabels(): array
    {
        return [
            self::REPEAT_UNIQUE_DIRECTS => 'Count each purchasing direct once',
            self::REPEAT_EACH_PURCHASE => 'Count every approved purchase row',
        ];
    }

    public static function doubleCreditPolicyLabels(): array
    {
        return [
            self::DOUBLE_ALLOW_SUM => 'Allow sum (joining + purchases may credit the same person twice)',
            self::DOUBLE_PREVENT_OVERLAP => 'Prevent overlap (joining only for non-purchasing directs + purchase metric)',
            self::DOUBLE_MAX_ONLY => 'Max only (use the larger of joining vs purchase metrics)',
        ];
    }

    private function eligibility(): ReferralLevelEligibility
    {
        return app(ReferralLevelEligibility::class);
    }

    public function normalizeBasis(?string $basis): string
    {
        $basis = (string) $basis;
        // Legacy plural key from earlier local drafts → canonical singular.
        if ($basis === 'combined_joining_and_purchases') {
            return self::BASIS_COMBINED_JOINING_AND_PURCHASE;
        }
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
        return (int) $this->breakdownForVersion($memberId, $version)['total_metric'];
    }

    /**
     * Explicit joining / purchase contribution breakdown for a version.
     *
     * @return array<string, mixed>
     */
    public function breakdownForVersion(string $memberId, LevelIncomeRuleVersion $version): array
    {
        $basis = $this->normalizeBasis($version->direct_qualification_basis ?? null);
        $tourOnly = (bool) ($version->tour_only_qualifies_for_unlock ?? false);

        if ($basis === self::BASIS_COMBINED_JOINING_AND_PURCHASE) {
            return $this->combinedBreakdown($memberId, $version, $tourOnly);
        }

        $metric = match ($basis) {
            self::BASIS_REGISTERED_DIRECTS => $this->eligibility()->directCount($memberId),
            self::BASIS_APPROVED_PACKAGE_DIRECTS => $this->uniqueDirectsWithApprovedPackages($memberId, $tourOnly, false),
            self::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT => $this->approvedPurchaseCountByDirects($memberId, $tourOnly, false),
            default => $this->uniqueDirectsWithApprovedPackages($memberId, $tourOnly, true),
        };

        return [
            'mode' => 'single_basis',
            'basis' => $basis,
            'tour_only_qualifies_for_unlock' => $tourOnly,
            'joining_enabled' => $basis === self::BASIS_REGISTERED_DIRECTS,
            'purchase_enabled' => $basis !== self::BASIS_REGISTERED_DIRECTS,
            'joining_raw' => $basis === self::BASIS_REGISTERED_DIRECTS ? $metric : 0,
            'purchase_raw' => $basis === self::BASIS_REGISTERED_DIRECTS ? 0 : $metric,
            'joining_weighted' => $basis === self::BASIS_REGISTERED_DIRECTS ? $metric : 0,
            'purchase_weighted' => $basis === self::BASIS_REGISTERED_DIRECTS ? 0 : $metric,
            'overlapping_directs' => 0,
            'double_credit_policy' => null,
            'purchase_scope' => $basis === self::BASIS_REGISTERED_DIRECTS ? null : $basis,
            'repeat_purchase_mode' => $basis === self::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT
                ? self::REPEAT_EACH_PURCHASE
                : self::REPEAT_UNIQUE_DIRECTS,
            'total_metric' => $metric,
            'formula_note' => 'Single qualification basis (legacy/default style). Combined joining+purchase is not active.',
        ];
    }

    public function isPersonallyActivatedForVersion(string $memberId, ?LevelIncomeRuleVersion $version): bool
    {
        if ($version === null) {
            return app(LevelIncomeRuleResolver::class)->isPersonallyActivated($memberId);
        }

        $basis = $this->normalizeBasis($version->direct_qualification_basis ?? null);
        $tourOnly = (bool) ($version->tour_only_qualifies_for_unlock ?? false);
        $scope = $this->normalizePurchaseScope($version->combined_purchase_scope ?? null);
        // Personal activation follows package filter for the active purchase-related setting.
        $incomeEligibleOnly = $basis === self::BASIS_INCOME_ELIGIBLE_DIRECTS
            || ($basis === self::BASIS_COMBINED_JOINING_AND_PURCHASE && $scope === self::BASIS_INCOME_ELIGIBLE_DIRECTS);

        // Personal activation is always about the evaluated member's own approved purchase.
        return Member::query()
            ->where('member_id', $memberId)
            ->whereExists(function ($query) use ($tourOnly, $incomeEligibleOnly): void {
                $this->approvedPurchaseExistsConstraint($query, $tourOnly, $incomeEligibleOnly);
            })
            ->exists();
    }

    public function normalizePurchaseScope(?string $scope): string
    {
        $scope = (string) $scope;
        if (! in_array($scope, self::PURCHASE_SCOPES, true)) {
            return self::BASIS_APPROVED_PACKAGE_DIRECTS;
        }

        return $scope;
    }

    public function normalizeRepeatMode(?string $mode): string
    {
        $mode = (string) $mode;
        if (! in_array($mode, self::REPEAT_MODES, true)) {
            return self::REPEAT_UNIQUE_DIRECTS;
        }

        return $mode;
    }

    public function normalizeDoubleCreditPolicy(?string $policy): string
    {
        $policy = (string) $policy;
        if (! in_array($policy, self::DOUBLE_CREDIT_POLICIES, true)) {
            return self::DOUBLE_PREVENT_OVERLAP;
        }

        return $policy;
    }

    /**
     * @return array<string, mixed>
     */
    private function combinedBreakdown(string $memberId, LevelIncomeRuleVersion $version, bool $tourOnly): array
    {
        $joiningEnabled = (bool) ($version->combined_joining_enabled ?? false);
        $purchaseEnabled = (bool) ($version->combined_purchase_enabled ?? false);
        $scope = $this->normalizePurchaseScope($version->combined_purchase_scope ?? null);
        $repeat = $this->normalizeRepeatMode($version->combined_repeat_purchase_mode ?? null);
        $policy = $this->normalizeDoubleCreditPolicy($version->combined_double_credit_policy ?? null);
        $joinUnit = max(0, (int) ($version->combined_joining_unit_value ?? 1));
        $purchaseUnit = max(0, (int) ($version->combined_purchase_unit_value ?? 1));

        $registered = $this->eligibility()->directCount($memberId);
        $personalPurchaseRows = $this->personalApprovedPurchaseCount($memberId, $tourOnly, false);
        $personalIncomeEligibleRows = $this->personalApprovedPurchaseCount($memberId, $tourOnly, true);

        if ($scope === self::BASIS_PERSONAL_APPROVED_PURCHASES) {
            $purchaseRows = $personalPurchaseRows;
            $purchasingUnique = $purchaseRows > 0 ? 1 : 0;
        } else {
            $purchasingUnique = $this->purchaseUniqueDirectCount($memberId, $scope, $tourOnly);
            $purchaseRows = $this->approvedPurchaseCountByDirects(
                $memberId,
                $tourOnly,
                $scope === self::BASIS_INCOME_ELIGIBLE_DIRECTS
            );
        }

        // When scope is unique directs, repeat mode unique is natural; each_purchase uses row count.
        $purchaseRaw = 0;
        if ($purchaseEnabled) {
            if ($scope === self::BASIS_PERSONAL_APPROVED_PURCHASES) {
                $purchaseRaw = $repeat === self::REPEAT_EACH_PURCHASE ? $purchaseRows : $purchasingUnique;
            } elseif ($scope === self::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT || $repeat === self::REPEAT_EACH_PURCHASE) {
                $purchaseRaw = $purchaseRows;
            } else {
                $purchaseRaw = $purchasingUnique;
            }
        }

        $joiningRaw = $joiningEnabled ? $registered : 0;
        // Overlap only applies when both sides count the same directs (not personal-purchase scope).
        $overlapping = ($joiningEnabled && $purchaseEnabled && $scope !== self::BASIS_PERSONAL_APPROVED_PURCHASES)
            ? $purchasingUnique
            : 0;

        $joiningForPolicy = $joiningRaw;
        if (
            $policy === self::DOUBLE_PREVENT_OVERLAP
            && $joiningEnabled
            && $purchaseEnabled
            && $scope !== self::BASIS_PERSONAL_APPROVED_PURCHASES
        ) {
            // Joining contributes only for directs without a counted purchase.
            $joiningForPolicy = max(0, $registered - $purchasingUnique);
        }

        $joiningWeighted = $joiningEnabled ? ($joiningForPolicy * $joinUnit) : 0;
        $purchaseWeighted = $purchaseEnabled ? ($purchaseRaw * $purchaseUnit) : 0;

        $total = match ($policy) {
            self::DOUBLE_MAX_ONLY => max($joiningWeighted, $purchaseWeighted),
            self::DOUBLE_ALLOW_SUM => $joiningWeighted + $purchaseWeighted,
            default => $joiningWeighted + $purchaseWeighted, // prevent_overlap already adjusted joining
        };

        if (! $joiningEnabled && ! $purchaseEnabled) {
            $total = 0;
        }

        return [
            'mode' => 'combined',
            'basis' => self::BASIS_COMBINED_JOINING_AND_PURCHASE,
            'tour_only_qualifies_for_unlock' => $tourOnly,
            'joining_enabled' => $joiningEnabled,
            'purchase_enabled' => $purchaseEnabled,
            'joining_raw' => $joiningRaw,
            'purchase_raw' => $purchaseRaw,
            'joining_weighted' => $joiningWeighted,
            'purchase_weighted' => $purchaseWeighted,
            'overlapping_directs' => $overlapping,
            'joining_unit_value' => $joinUnit,
            'purchase_unit_value' => $purchaseUnit,
            'double_credit_policy' => $policy,
            'purchase_scope' => $scope,
            'repeat_purchase_mode' => $repeat,
            'registered_direct_count' => $registered,
            'purchasing_unique_direct_count' => $purchasingUnique,
            'approved_purchase_row_count' => $purchaseRows,
            'personal_approved_purchase_count' => $personalPurchaseRows,
            'personal_income_eligible_purchase_count' => $personalIncomeEligibleRows,
            'total_metric' => $total,
            'formula_note' => $this->combinedFormulaNote($joiningEnabled, $purchaseEnabled, $policy, $scope, $repeat, $joinUnit, $purchaseUnit),
            'requires_business_approval' => true,
        ];
    }

    private function combinedFormulaNote(
        bool $joiningEnabled,
        bool $purchaseEnabled,
        string $policy,
        string $scope,
        string $repeat,
        int $joinUnit,
        int $purchaseUnit
    ): string {
        if (! $joiningEnabled && ! $purchaseEnabled) {
            return 'Combined mode selected but both contributions are disabled — metric is 0 until Admin enables joining and/or purchases.';
        }

        return sprintf(
            'Combined metric (draft/unauthorized until BD approves): joining=%s×%d, purchases=%s/%s×%d, double_credit=%s.',
            $joiningEnabled ? 'on' : 'off',
            $joinUnit,
            $purchaseEnabled ? $scope : 'off',
            $repeat,
            $purchaseUnit,
            $policy
        );
    }

    private function purchaseUniqueDirectCount(string $memberId, string $scope, bool $tourOnly): int
    {
        return match ($scope) {
            self::BASIS_INCOME_ELIGIBLE_DIRECTS => $this->uniqueDirectsWithApprovedPackages($memberId, $tourOnly, true),
            self::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT => $this->uniqueDirectsWithApprovedPackages($memberId, $tourOnly, false),
            default => $this->uniqueDirectsWithApprovedPackages($memberId, $tourOnly, false),
        };
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

        // Canonical live entitlement while unauthorized: legacy ladder ignores personal activation.
        $liveUnlocked = $resolver->legacyLevelsForCount($incomeEligibleDirects);
        $displayUnlocked = $resolver->unlockedLevels($incomeEligibleDirects, $activatedLegacy);

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
                    self::BASIS_COMBINED_JOINING_AND_PURCHASE => (int) $this->combinedBreakdown(
                        $memberId,
                        new LevelIncomeRuleVersion([
                            'direct_qualification_basis' => self::BASIS_COMBINED_JOINING_AND_PURCHASE,
                            'tour_only_qualifies_for_unlock' => $tourFlag,
                            'combined_joining_enabled' => true,
                            'combined_purchase_enabled' => true,
                            'combined_purchase_scope' => self::BASIS_APPROVED_PACKAGE_DIRECTS,
                            'combined_repeat_purchase_mode' => self::REPEAT_UNIQUE_DIRECTS,
                            'combined_double_credit_policy' => self::DOUBLE_PREVENT_OVERLAP,
                            'combined_joining_unit_value' => 1,
                            'combined_purchase_unit_value' => 1,
                        ]),
                        $tourFlag
                    )['total_metric'],
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
        $proposedUnlocked = 0;
        if ($candidate !== null) {
            $candidate->loadMissing('bands');
            $cCount = $this->countForVersion($memberId, $candidate);
            $cActivated = $this->isPersonallyActivatedForVersion($memberId, $candidate);
            $proposedUnlocked = $resolver->simulateUnlockedLevels(
                $candidate->bands->map(fn ($b): array => [
                    'start_level' => (int) $b->start_level,
                    'end_level' => (int) $b->end_level,
                    'min_qualifying_directs' => (int) $b->min_qualifying_directs,
                    'requires_personal_activation' => (bool) $b->requires_personal_activation,
                ])->all(),
                $cCount,
                $cActivated
            );
            $cBreakdown = $this->breakdownForVersion($memberId, $candidate);
            $candidateExpected = [
                'version_code' => $candidate->version_code,
                'status' => $candidate->status,
                'posting_authorized' => (bool) $candidate->posting_authorized,
                'direct_qualification_basis' => $this->normalizeBasis($candidate->direct_qualification_basis ?? null),
                'tour_only_qualifies_for_unlock' => (bool) ($candidate->tour_only_qualifies_for_unlock ?? false),
                'direct_metric' => $cCount,
                'personally_activated' => $cActivated,
                'expected_unlocked_levels' => $proposedUnlocked,
                'qualification_breakdown' => $cBreakdown,
            ];
        }

        $reason = match (true) {
            $liveUnlocked > 0 => sprintf(
                'Live legacy entitlement: %d level(s) from %d income-eligible unique direct(s). Legacy ladder ignores personal activation.',
                $liveUnlocked,
                $incomeEligibleDirects
            ),
            ! $activatedLegacy && $liveUnlocked === 0 => 'No income-eligible personal activation under live legacy rules (activation is informational only for legacy unlock).',
            $incomeEligibleDirects === 0 && $liveUnlocked === 0 => 'Live legacy unlock needs ≥1 income-eligible qualifying direct (registration / Tour Only alone do not count).',
            default => 'Locked under live legacy ladder.',
        };

        $deltaReason = null;
        if ($candidateExpected !== null) {
            if ($liveUnlocked > 0 && $proposedUnlocked === 0 && ! ($candidateExpected['personally_activated'] ?? false)) {
                $deltaReason = sprintf(
                    'ENTITLEMENT REDUCTION RISK: live %d via legacy directs; proposed bands require personal activation (basis=%s, tour_only_unlock=%s) and member is not activated under that filter → proposed 0. Live unchanged until authorize.',
                    $liveUnlocked,
                    $candidateExpected['direct_qualification_basis'],
                    ($candidateExpected['tour_only_qualifies_for_unlock'] ?? false) ? 'yes' : 'no'
                );
            } elseif ($liveUnlocked !== $proposedUnlocked) {
                $deltaReason = sprintf(
                    'Proposed simulation %d vs live legacy %d (proposed_activated=%s, proposed_metric=%d). Not applied.',
                    $proposedUnlocked,
                    $liveUnlocked,
                    ($candidateExpected['personally_activated'] ?? false) ? 'yes' : 'no',
                    (int) ($candidateExpected['direct_metric'] ?? 0)
                );
            } else {
                $deltaReason = 'Proposed simulation matches live legacy entitlement.';
            }
        }

        return [
            'member_id' => $memberId,
            'eligibility_context' => LevelIncomeUnlockImpactAnalyzer::CONTEXT_LIVE_LEGACY,
            'registered_direct_count' => $registered,
            'income_eligible_direct_count' => $incomeEligibleDirects,
            'approved_package_direct_count' => $approvedPackageDirects,
            'approved_package_direct_count_excluding_tour_only' => $approvedPackageDirectsNoTour,
            'tour_only_purchasing_direct_count' => $tourOnlyDirects,
            'approved_direct_package_purchase_count' => $purchaseCountInclTour,
            'income_eligible_direct_package_purchase_count' => $purchaseCountIncomeOnly,
            'currently_unlocked_levels' => $liveUnlocked,
            'display_unlocked_levels' => $displayUnlocked,
            'live_rule_mode' => $authorized ? LevelIncomeRuleResolver::MODE_AUTHORIZED : LevelIncomeRuleResolver::MODE_LEGACY,
            'applicable_version' => $authorized?->version_code ?? 'legacy_hardcoded',
            'locked_reason' => $reason,
            'proposed_delta_reason' => $deltaReason,
            'entitlement_reduction' => $liveUnlocked > $proposedUnlocked,
            'candidate_expected' => $candidateExpected,
            'expected_under_each_mode' => $modeExpected,
            'note' => 'Previews do not authorize posting. currently_unlocked_levels is always live legacy entitlement. Accrual is purchase-scoped when authorized. Total Business (Tour Only BV) remains separate from unlock.',
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

    private function personalApprovedPurchaseCount(string $memberId, bool $includeTourOnly, bool $incomeEligibleOnly): int
    {
        $query = PackagePurchase::query()
            ->where('member_id', $memberId)
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
