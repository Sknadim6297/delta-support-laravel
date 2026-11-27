<?php

namespace App\Services;

use App\Models\LevelIncomeRuleVersion;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Independent Level Unlock verification calculator.
 *
 * Expected levels are derived ONLY from raw DB counts using the Option B personal formula:
 *   expected = activated ? min(20, 4 × (joining + approved_personal_purchases)) : 0
 * Grandfather: protected = max(proposed, min(snapshot_legacy, current_legacy))
 *
 * Does NOT call LevelIncomeRuleResolver / LevelIncomeDirectQualification for expected values.
 * Runtime/dashboard columns are read separately for comparison only.
 */
class IndependentLevelUnlockAuditor
{
    /** @var list<string> */
    public const BUSINESS_VOLUME_TYPES = [
        Package::TYPE_TOUR,
        Package::TYPE_TOUR_ROI,
        Package::TYPE_PREMIUM,
        Package::TYPE_ROI,
    ];

    /** @var list<string> Income-eligible types (Tour Only excluded — no ROI/commission generation). */
    public const INCOME_ELIGIBLE_TYPES = [
        Package::TYPE_TOUR_ROI,
        Package::TYPE_PREMIUM,
        Package::TYPE_ROI,
    ];

    public function businessTimezone(): string
    {
        return (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveVersion(?string $versionCode): array
    {
        $requested = is_string($versionCode) ? trim($versionCode) : '';
        $query = LevelIncomeRuleVersion::query()->with(['bands', 'rates']);

        if ($requested !== '') {
            $exact = (clone $query)->where('version_code', $requested)->first();

            return [
                'requested' => $requested,
                'found' => $exact !== null,
                'version' => $exact,
                'selection' => $exact !== null ? 'exact_version_code' : 'not_found',
            ];
        }

        $authorized = (clone $query)
            ->where('status', LevelIncomeRuleVersion::STATUS_PUBLISHED)
            ->where('posting_authorized', true)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();

        return [
            'requested' => null,
            'found' => $authorized !== null,
            'version' => $authorized,
            'selection' => $authorized !== null ? 'current_posting_authorized' : 'none',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function verifyAllMembers(LevelIncomeRuleVersion $version, string $businessDate): array
    {
        $businessDate = CarbonImmutable::parse($businessDate, $this->businessTimezone())->toDateString();
        $memberIds = Member::query()->orderBy('id')->pluck('member_id')->all();
        $rows = [];
        foreach ($memberIds as $memberId) {
            $rows[] = $this->verifyMember((string) $memberId, $version, $businessDate);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyMember(string $memberId, LevelIncomeRuleVersion $version, string $businessDate): array
    {
        $includeTourForUnlock = (bool) ($version->tour_only_qualifies_for_unlock ?? false);
        $purchaseTypes = $includeTourForUnlock
            ? self::BUSINESS_VOLUME_TYPES
            : array_values(array_filter(
                self::BUSINESS_VOLUME_TYPES,
                static fn (string $t): bool => $t !== Package::TYPE_TOUR
            ));

        $joining = (int) Member::query()
            ->where('sponsor_id', $memberId)
            ->where('member_id', '!=', $memberId)
            ->count();

        $approvedPurchases = $this->countApprovedPurchases($memberId, $purchaseTypes);
        $pendingPurchases = $this->countPurchasesByStatus($memberId, PackagePurchase::PENDING);
        $rejectedPurchases = $this->countPurchasesByStatus($memberId, PackagePurchase::REJECTED);
        $tourOnlyApproved = $this->countApprovedPurchases($memberId, [Package::TYPE_TOUR]);
        $incomeEligibleOwn = $this->countApprovedPurchases($memberId, self::INCOME_ELIGIBLE_TYPES);

        // Personal activation: own approved purchase in unlock-qualified types (Tour included when flag on).
        $activated = $approvedPurchases > 0;

        $metric = $joining + $approvedPurchases;
        $expectedProposed = $activated
            ? min(ReferralLevelEligibility::MAX_LEVELS, 4 * $metric)
            : 0;

        // Band cross-check from DB bands only (not resolver).
        $bandExpected = $this->expectedFromBands($version, $metric, $activated);

        $incomeEligibleDirects = $this->countIncomeEligibleDirects($memberId);
        $legacyLevel = $this->legacyLevelsForCount($incomeEligibleDirects);

        $snapshot = $this->snapshotLegacy($version, $memberId);
        $grandfatherEnabled = (bool) ($version->grandfather_existing_unlock ?? false);
        if ($grandfatherEnabled && $snapshot !== null) {
            $protectedLevel = max($expectedProposed, min($snapshot, $legacyLevel));
        } else {
            // No snapshot / grandfather off → proposed only (matches runtime post-capture policy).
            $protectedLevel = $expectedProposed;
        }
        $protectedLevel = max(0, min($protectedLevel, ReferralLevelEligibility::MAX_LEVELS));

        // Runtime / dashboard (comparison only — not used for expected calculation).
        $eligibility = app(ReferralLevelEligibility::class);
        $display = $eligibility->forMember($memberId);
        $runtimeLevel = (int) ($display['unlocked_levels'] ?? 0);
        $dashboardLevel = $runtimeLevel; // member + admin both use forMember() without purchase context
        $adminDashboardLevel = $runtimeLevel;

        $effective = optional($version->effective_from)?->toDateString();
        $authorizedLive = (bool) $version->posting_authorized
            && $version->status === LevelIncomeRuleVersion::STATUS_PUBLISHED;
        $onOrAfterEffective = $effective !== null && $businessDate >= $effective;

        // Purchase-scoped runtime for an own approved purchase when present (accrual path).
        $accrualRuntime = null;
        $ownPurchase = PackagePurchase::query()
            ->where('member_id', $memberId)
            ->where('status', PackagePurchase::APPROVED)
            ->whereNotNull('reviewed_at')
            ->orderBy('id')
            ->first();
        if ($ownPurchase !== null) {
            $ctx = app(LevelIncomeRuleResolver::class)->postingContextFor($ownPurchase, $businessDate);
            $accrualSummary = $eligibility->forMember($memberId, $ownPurchase, $businessDate);
            $accrualRuntime = [
                'mode' => $ctx['mode'],
                'reason' => $ctx['reason'],
                'unlocked_levels' => (int) ($accrualSummary['unlocked_levels'] ?? 0),
            ];
        }

        $levelIncomeEligible = $incomeEligibleOwn > 0 || $incomeEligibleDirects > 0;

        $pass = $protectedLevel === $runtimeLevel
            && $bandExpected === $expectedProposed
            && $dashboardLevel === $runtimeLevel
            && $adminDashboardLevel === $runtimeLevel;

        $failReasons = [];
        if ($bandExpected !== $expectedProposed) {
            $failReasons[] = sprintf('band_expected(%d)!=formula_expected(%d)', $bandExpected, $expectedProposed);
        }
        if ($protectedLevel !== $runtimeLevel) {
            $failReasons[] = sprintf('protected(%d)!=runtime(%d)', $protectedLevel, $runtimeLevel);
        }
        if ($dashboardLevel !== $runtimeLevel) {
            $failReasons[] = 'dashboard!=runtime';
        }

        return [
            'member_id' => $memberId,
            'joining' => $joining,
            'approved_purchases' => $approvedPurchases,
            'pending_purchases' => $pendingPurchases,
            'rejected_purchases' => $rejectedPurchases,
            'tour_only_approved_purchases' => $tourOnlyApproved,
            'income_eligible_own_purchases' => $incomeEligibleOwn,
            'activated' => $activated,
            'metric' => $metric,
            'legacy_level' => $legacyLevel,
            'income_eligible_directs' => $incomeEligibleDirects,
            'snapshot_legacy' => $snapshot,
            'expected_level' => $expectedProposed,
            'band_expected_level' => $bandExpected,
            'protected_level' => $protectedLevel,
            'runtime_level' => $runtimeLevel,
            'dashboard_level' => $dashboardLevel,
            'admin_dashboard_level' => $adminDashboardLevel,
            'runtime_rule_mode' => $display['rule_mode'] ?? null,
            'business_date' => $businessDate,
            'effective_from' => $effective,
            'on_or_after_effective' => $onOrAfterEffective,
            'version_posting_authorized' => $authorizedLive,
            'accrual_runtime' => $accrualRuntime,
            'level_income_eligible' => $levelIncomeEligible,
            'pass' => $pass,
            'fail_reasons' => $failReasons,
            'result' => $pass ? 'PASS' : 'FAIL',
        ];
    }

    /**
     * @param  list<string>  $types
     */
    public function countApprovedPurchases(string $memberId, array $types): int
    {
        if ($types === []) {
            return 0;
        }

        return (int) DB::table('package_purchases')
            ->where('member_id', $memberId)
            ->where('status', PackagePurchase::APPROVED)
            ->whereNotNull('reviewed_at')
            ->whereIn('package_type', $types)
            ->count();
    }

    public function countPurchasesByStatus(string $memberId, string $status): int
    {
        return (int) DB::table('package_purchases')
            ->where('member_id', $memberId)
            ->where('status', $status)
            ->count();
    }

    public function countIncomeEligibleDirects(string $memberId): int
    {
        return (int) DB::table('members as d')
            ->where('d.sponsor_id', $memberId)
            ->where('d.member_id', '!=', $memberId)
            ->whereExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('package_purchases as p')
                    ->whereColumn('p.member_id', 'd.member_id')
                    ->where('p.status', PackagePurchase::APPROVED)
                    ->whereNotNull('p.reviewed_at')
                    ->whereIn('p.package_type', self::INCOME_ELIGIBLE_TYPES);
            })
            ->count();
    }

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

    public function expectedFromBands(LevelIncomeRuleVersion $version, int $metric, bool $activated): int
    {
        $bandTable = Schema::hasTable('level_income_unlock_bands')
            ? 'level_income_unlock_bands'
            : (Schema::hasTable('level_income_rule_bands') ? 'level_income_rule_bands' : null);
        if ($bandTable === null) {
            return 0;
        }

        $bands = DB::table($bandTable)
            ->where('level_income_rule_version_id', $version->id)
            ->orderBy('start_level')
            ->get(['start_level', 'end_level', 'min_qualifying_directs', 'requires_personal_activation']);

        $unlocked = 0;
        foreach ($bands as $band) {
            if ((bool) $band->requires_personal_activation && ! $activated) {
                continue;
            }
            if ($metric < (int) $band->min_qualifying_directs) {
                continue;
            }
            $unlocked = max($unlocked, (int) $band->end_level);
        }

        return min($unlocked, ReferralLevelEligibility::MAX_LEVELS);
    }

    public function snapshotLegacy(LevelIncomeRuleVersion $version, string $memberId): ?int
    {
        if (! Schema::hasTable('level_income_unlock_grandfather_snapshots')) {
            return null;
        }

        $row = DB::table('level_income_unlock_grandfather_snapshots')
            ->where('level_income_rule_version_id', $version->id)
            ->where('member_id', $memberId)
            ->first(['legacy_unlocked_levels']);

        return $row === null ? null : (int) $row->legacy_unlocked_levels;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function summarize(array $rows): array
    {
        $pass = 0;
        $fail = 0;
        $mismatches = [];
        foreach ($rows as $row) {
            if ($row['pass']) {
                $pass++;
            } else {
                $fail++;
                $mismatches[] = [
                    'member_id' => $row['member_id'],
                    'fail_reasons' => $row['fail_reasons'],
                    'joining' => $row['joining'],
                    'approved_purchases' => $row['approved_purchases'],
                    'expected_level' => $row['expected_level'],
                    'protected_level' => $row['protected_level'],
                    'runtime_level' => $row['runtime_level'],
                ];
            }
        }

        return [
            'total_members' => count($rows),
            'pass' => $pass,
            'fail' => $fail,
            'all_pass' => $fail === 0 && count($rows) > 0,
            'mismatches' => $mismatches,
        ];
    }
}
