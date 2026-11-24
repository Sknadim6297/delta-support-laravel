<?php

namespace App\Services;

use App\Models\LevelIncomeRuleVersion;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only Level Unlock impact analysis with explicitly labeled eligibility contexts.
 * Never mutates wallets, accruals, purchases, or authorization flags.
 */
class LevelIncomeUnlockImpactAnalyzer
{
    public const CONTEXT_LIVE_LEGACY = 'live_legacy';

    public const CONTEXT_PROPOSED_SIMULATION = 'proposed_simulation';

    public const CONTEXT_AUTHORIZED_DISPLAY = 'authorized_display_without_purchase';

    public const CONTEXT_PURCHASE_ACCRUAL = 'purchase_accrual';

    public const IMPACT_GAIN = 'gain';

    public const IMPACT_LOSS = 'loss';

    public const IMPACT_UNCHANGED = 'unchanged';

    public function __construct(
        private readonly ReferralLevelEligibility $eligibility,
        private readonly LevelIncomeDirectQualification $qualification,
        private readonly LevelIncomeRuleResolver $resolver,
        private readonly PackageBusinessVolumeEligibility $volume,
    ) {
    }

    public function proposedVersion(): ?LevelIncomeRuleVersion
    {
        if (! Schema::hasTable('level_income_rule_versions')) {
            return null;
        }

        return LevelIncomeRuleVersion::query()
            ->with('bands')
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->first();
    }

    /**
     * Unified per-member unlock impact. Live "current" always means hard-coded legacy
     * entitlement (income-eligible unique directs; personal activation ignored by ladder).
     *
     * @return array<string, mixed>
     */
    public function analyzeMember(string $memberId, ?LevelIncomeRuleVersion $candidate = null): array
    {
        $candidate ??= $this->proposedVersion();
        $authorized = $this->resolver->authorizedVersion();

        $registered = $this->eligibility->directCount($memberId);
        $incomeEligibleDirects = $this->eligibility->qualifyingDirectCount($memberId);
        $approvedPackageDirects = $this->qualificationCount(
            $memberId,
            LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            true
        );
        $approvedPackageDirectsNoTour = $this->qualificationCount(
            $memberId,
            LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            false
        );
        $purchaseCount = $this->qualificationCount(
            $memberId,
            LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT,
            true
        );
        $tourOnlyDirects = $this->tourOnlyPurchasingDirectCount($memberId);

        $legacyActivated = $this->resolver->isPersonallyActivated($memberId);
        // Legacy ladder ignores personal activation — this is the live entitlement while unauthorized.
        $liveLegacyUnlocked = $this->resolver->legacyLevelsForCount($incomeEligibleDirects);

        $displayWithoutPurchase = $this->eligibility->forMember($memberId);
        $displayUnlocked = (int) $displayWithoutPurchase['unlocked_levels'];

        $candidateMetric = null;
        $candidateActivated = false;
        $proposedUnlocked = 0;
        $candidateMeta = null;
        $protection = null;
        $protectedUnlocked = 0;
        if ($candidate !== null) {
            $candidateMetric = $this->qualification->countForVersion($memberId, $candidate);
            $candidateActivated = $this->qualification->isPersonallyActivatedForVersion($memberId, $candidate);
            $proposedUnlocked = $this->simulate($candidate, $candidateMetric, $candidateActivated);
            $candidateMeta = [
                'version_code' => $candidate->version_code,
                'status' => $candidate->status,
                'posting_authorized' => (bool) $candidate->posting_authorized,
                'direct_qualification_basis' => $this->qualification->normalizeBasis($candidate->direct_qualification_basis ?? null),
                'tour_only_qualifies_for_unlock' => (bool) ($candidate->tour_only_qualifies_for_unlock ?? false),
                'grandfather_existing_unlock' => (bool) ($candidate->grandfather_existing_unlock ?? false),
                'direct_metric' => $candidateMetric,
                'personally_activated' => $candidateActivated,
            ];
            $protection = app(LevelIncomeUnlockGrandfathering::class)
                ->previewMember($memberId, $candidate, $proposedUnlocked);
            $protectedUnlocked = (int) $protection['protected'];
        } else {
            $protectedUnlocked = $liveLegacyUnlocked;
        }

        // Impact vs live uses protected levels when grandfathering is in play.
        $compareUnlock = $protectedUnlocked;
        $impact = match (true) {
            $compareUnlock > $liveLegacyUnlocked => self::IMPACT_GAIN,
            $compareUnlock < $liveLegacyUnlocked => self::IMPACT_LOSS,
            default => self::IMPACT_UNCHANGED,
        };

        $deltaReason = $this->deltaReason(
            $liveLegacyUnlocked,
            $proposedUnlocked,
            $legacyActivated,
            $candidateActivated,
            $incomeEligibleDirects,
            $candidateMetric ?? 0,
            $candidateMeta
        );
        if ($protection !== null && ($protection['grandfather_enabled'] ?? false) && $protectedUnlocked > $proposedUnlocked) {
            $deltaReason = 'PROTECTED: '.$protection['reason'].' Proposed raw='.$proposedUnlocked.'; protected='.$protectedUnlocked.'.';
        }

        $liveReason = match (true) {
            $liveLegacyUnlocked > 0 => sprintf(
                'Live legacy entitlement: %d level(s) from %d income-eligible unique direct(s). Legacy ladder ignores personal activation.',
                $liveLegacyUnlocked,
                $incomeEligibleDirects
            ),
            $legacyActivated && $incomeEligibleDirects === 0 => 'Personally activated (income-eligible package) but 0 income-eligible directs — legacy unlock stays 0.',
            ! $legacyActivated && $incomeEligibleDirects === 0 => 'No income-eligible personal activation and no income-eligible directs under live legacy rules.',
            default => 'Locked under live legacy ladder.',
        };

        return [
            'member_id' => $memberId,
            'contexts' => [
                self::CONTEXT_LIVE_LEGACY => [
                    'label' => 'Live hard-coded legacy entitlement (unauthorized posting)',
                    'unlocked_levels' => $liveLegacyUnlocked,
                    'direct_metric' => $incomeEligibleDirects,
                    'direct_metric_basis' => LevelIncomeDirectQualification::BASIS_INCOME_ELIGIBLE_DIRECTS,
                    'personally_activated' => $legacyActivated,
                    'personal_activation_affects_unlock' => false,
                    'source_purchase_id' => null,
                    'business_date' => null,
                    'reason' => $liveReason,
                ],
                self::CONTEXT_PROPOSED_SIMULATION => [
                    'label' => 'Proposed simulation only — not posting-authorized',
                    'unlocked_levels' => $proposedUnlocked,
                    'direct_metric' => $candidateMetric,
                    'direct_metric_basis' => $candidateMeta['direct_qualification_basis'] ?? null,
                    'personally_activated' => $candidateActivated,
                    'personal_activation_affects_unlock' => true,
                    'source_purchase_id' => null,
                    'business_date' => null,
                    'version' => $candidateMeta,
                    'reason' => $deltaReason,
                ],
                self::CONTEXT_AUTHORIZED_DISPLAY => [
                    'label' => 'Member/Admin display without a source purchase (may differ from purchase-scoped accrual when authorized)',
                    'unlocked_levels' => $displayUnlocked,
                    'rule_mode' => $displayWithoutPurchase['rule_mode'] ?? LevelIncomeRuleResolver::MODE_LEGACY,
                    'source_purchase_id' => null,
                    'business_date' => null,
                    'posting_authorized_version' => $authorized?->version_code,
                    'note' => $authorized === null
                        ? 'No posting-authorized version; display matches live legacy.'
                        : 'A posting-authorized version exists. Accrual still selects legacy vs authorized per source purchase + business date.',
                ],
            ],
            'counts' => [
                'registered_direct_count' => $registered,
                'income_eligible_direct_count' => $incomeEligibleDirects,
                'approved_package_direct_count' => $approvedPackageDirects,
                'approved_package_direct_count_excluding_tour_only' => $approvedPackageDirectsNoTour,
                'approved_direct_package_purchase_count' => $purchaseCount,
                'tour_only_purchasing_direct_count' => $tourOnlyDirects,
            ],
            // Canonical fields used by diagnostic tables — always identical for the same member.
            'live_unlocked_levels' => $liveLegacyUnlocked,
            'proposed_unlocked_levels' => $proposedUnlocked,
            'protected_unlocked_levels' => $protectedUnlocked,
            'display_unlocked_levels' => $displayUnlocked,
            'grandfather_preview' => $protection,
            'impact' => $impact,
            'levels_delta' => $compareUnlock - $liveLegacyUnlocked,
            'entitlement_reduction' => $impact === self::IMPACT_LOSS,
            'raw_proposed_reduction' => $proposedUnlocked < $liveLegacyUnlocked,
            'delta_reason' => $deltaReason,
            'live_reason' => $liveReason,
            'legacy_personally_activated' => $legacyActivated,
            'proposed_personally_activated' => $candidateActivated,
            'activated_with_zero_live_unlock' => $legacyActivated && $liveLegacyUnlocked === 0,
        ];
    }

    /**
     * @param  list<string>  $memberIds
     * @return array{rows: list<array<string, mixed>>, summary: array<string, int>}
     */
    public function analyzeMembers(array $memberIds, ?LevelIncomeRuleVersion $candidate = null): array
    {
        $candidate ??= $this->proposedVersion();
        $rows = [];
        $summary = [
            'analyzed' => 0,
            'gain' => 0,
            'loss' => 0,
            'unchanged' => 0,
            'entitlement_reductions' => 0,
            'activated_with_zero_live_unlock' => 0,
        ];

        foreach (array_values(array_unique($memberIds)) as $memberId) {
            $row = $this->analyzeMember($memberId, $candidate);
            $rows[] = $row;
            $summary['analyzed']++;
            $summary[$row['impact']]++;
            if ($row['entitlement_reduction']) {
                $summary['entitlement_reductions']++;
            }
            if ($row['activated_with_zero_live_unlock']) {
                $summary['activated_with_zero_live_unlock']++;
            }
        }

        return ['rows' => $rows, 'summary' => $summary];
    }

    /**
     * Purchase-scoped accrual context for a specific source purchase + business date.
     *
     * @return array<string, mixed>
     */
    public function purchaseAccrualContext(string $beneficiaryMemberId, PackagePurchase $sourcePurchase, string $businessDate): array
    {
        $summary = $this->eligibility->forMember($beneficiaryMemberId, $sourcePurchase, $businessDate);
        $posting = $this->resolver->postingContextFor($sourcePurchase, $businessDate);

        return [
            'context' => self::CONTEXT_PURCHASE_ACCRUAL,
            'beneficiary_member_id' => $beneficiaryMemberId,
            'source_purchase_id' => $sourcePurchase->id,
            'source_purchase_member_id' => $sourcePurchase->member_id,
            'business_date' => $businessDate,
            'unlocked_levels' => (int) $summary['unlocked_levels'],
            'qualifying_direct_count' => (int) $summary['qualifying_direct_count'],
            'personally_activated' => (bool) $summary['personally_activated'],
            'rule_mode' => $summary['rule_mode'],
            'posting_context' => [
                'mode' => $posting['mode'],
                'applicability' => $posting['applicability'],
                'effective_from' => $posting['effective_from'],
                'purchase_approval_date' => $posting['purchase_approval_date'],
                'reason' => $posting['reason'],
                'version_code' => $posting['version']?->version_code,
            ],
        ];
    }

    /**
     * List every approved Tour Only BV purchase exactly once (snapshot price_usd).
     *
     * @return array<string, mixed>
     */
    public function tourOnlyBusinessVolumeAudit(): array
    {
        $query = $this->volume->applyPurchaseConstraints(DB::table('package_purchases'))
            ->where('package_type', Package::TYPE_TOUR)
            ->orderBy('id');

        $rows = (clone $query)->get([
            'id',
            'reference',
            'member_id',
            'package_code',
            'package_type',
            'price_usd',
            'reviewed_at',
            'status',
        ]);

        $seenIds = [];
        $purchases = [];
        $sum = '0.0000';
        foreach ($rows as $row) {
            $id = (int) $row->id;
            if (isset($seenIds[$id])) {
                continue;
            }
            $seenIds[$id] = true;
            $amount = bcadd((string) $row->price_usd, '0', 4);
            $sum = bcadd($sum, $amount, 4);
            $purchases[] = [
                'package_purchase_id' => $id,
                'reference' => $row->reference,
                'member_id' => $row->member_id,
                'package_code' => $row->package_code,
                'price_usd' => $amount,
                'reviewed_at' => $row->reviewed_at,
            ];
        }

        return [
            'context' => 'tour_only_business_volume',
            'purchase_count' => count($purchases),
            'unique_purchase_ids' => count($seenIds),
            'total_usdt' => $sum,
            'counted_once' => count($purchases) === count($seenIds),
            'purchases' => $purchases,
            'note' => 'Each approved Tour Only USDT snapshot is included once in BV. Does not generate ROI or Level Income.',
        ];
    }

    /**
     * How applicability would treat existing approved purchases if a candidate were authorized.
     * Read-only projection — does not authorize.
     *
     * @return array<string, mixed>
     */
    public function applicabilityProjection(?LevelIncomeRuleVersion $candidate = null, ?string $assumeEffectiveFrom = null): array
    {
        $candidate ??= $this->proposedVersion();
        $effective = $assumeEffectiveFrom
            ?? optional($candidate?->effective_from)?->toDateString()
            ?? now()->toDateString();

        $approvedMembers = Member::query()
            ->whereExists(function ($q): void {
                $q->selectRaw('1')->from('package_purchases')
                    ->whereColumn('package_purchases.member_id', 'members.member_id')
                    ->where('status', PackagePurchase::APPROVED)
                    ->whereNotNull('reviewed_at');
            })
            ->count();

        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $approvedPurchases = PackagePurchase::query()
            ->where('status', PackagePurchase::APPROVED)
            ->whereNotNull('reviewed_at')
            ->get(['id', 'member_id', 'reviewed_at']);

        $before = 0;
        $onOrAfter = 0;
        foreach ($approvedPurchases as $purchase) {
            $approval = $purchase->reviewed_at === null
                ? null
                : \Carbon\CarbonImmutable::parse($purchase->reviewed_at)->timezone($timezone)->toDateString();
            if ($approval === null || $approval < $effective) {
                $before++;
            } else {
                $onOrAfter++;
            }
        }

        return [
            'read_only' => true,
            'members_with_approved_packages' => $approvedMembers,
            'approved_purchase_rows' => $approvedPurchases->count(),
            'assumed_effective_from' => $effective,
            'future_purchases_only' => [
                'existing_purchases_keep_legacy' => $before + $onOrAfter, // all current rows already exist
                'purchases_approved_before_effective' => $before,
                'purchases_approved_on_or_after_effective' => $onOrAfter,
                'effect_on_existing_43_style_members' => 'Existing approved purchases keep hard-coded legacy unlock/rates for Level Income accrual. New purchases approved on/after effective_from use authorized rules. Member display without a purchase context must not be treated as accrual truth.',
            ],
            'existing_prospective' => [
                'business_dates_before_effective_keep_legacy' => true,
                'business_dates_on_or_after_effective_use_authorized' => true,
                'historical_backfill' => false,
                'effect_on_existing_43_style_members' => 'From effective business date forward, authorized unlock/rates apply to commissions generated from existing and new purchases. Prior business dates stay legacy. No wallet/history recalculation.',
            ],
            'authorization_status' => ($candidate?->posting_authorized ?? false) ? 'authorized' : 'unauthorized',
            'warning' => 'This projection does not authorize posting or change live accrual.',
        ];
    }

    /**
     * Client policy comparison for the four qualification bases.
     *
     * @return list<array<string, mixed>>
     */
    public function policyComparison(): array
    {
        return [
            [
                'basis' => LevelIncomeDirectQualification::BASIS_REGISTERED_DIRECTS,
                'client_fit' => 'Matches “registered directs” wording; unlocks without purchase — high entitlement expansion.',
                'unique_members' => true,
                'live_default' => false,
                'proposed_draft_default' => false,
            ],
            [
                'basis' => LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
                'client_fit' => 'Matches “direct members with approved packages” + optional Tour Only unlock flag. Proposed draft default.',
                'unique_members' => true,
                'live_default' => false,
                'proposed_draft_default' => true,
            ],
            [
                'basis' => LevelIncomeDirectQualification::BASIS_INCOME_ELIGIBLE_DIRECTS,
                'client_fit' => 'Matches current live commission semantics; Tour Only excluded unless unlock flag adds it.',
                'unique_members' => true,
                'live_default' => true,
                'proposed_draft_default' => false,
            ],
            [
                'basis' => LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT,
                'client_fit' => 'Matches “approved package purchase count”; not unique members — can inflate unlock vs unique-direct bands.',
                'unique_members' => false,
                'live_default' => false,
                'proposed_draft_default' => false,
            ],
        ];
    }

    private function qualificationCount(string $memberId, string $basis, bool $tourOnly): int
    {
        $stub = new LevelIncomeRuleVersion([
            'direct_qualification_basis' => $basis,
            'tour_only_qualifies_for_unlock' => $tourOnly,
        ]);

        return $this->qualification->countForVersion($memberId, $stub);
    }

    private function tourOnlyPurchasingDirectCount(string $memberId): int
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

    private function simulate(LevelIncomeRuleVersion $version, int $metric, bool $activated): int
    {
        $version->loadMissing('bands');
        if ($version->bands->isEmpty()) {
            return 0;
        }

        return $this->resolver->simulateUnlockedLevels(
            $version->bands->map(fn ($b): array => [
                'start_level' => (int) $b->start_level,
                'end_level' => (int) $b->end_level,
                'min_qualifying_directs' => (int) $b->min_qualifying_directs,
                'requires_personal_activation' => (bool) $b->requires_personal_activation,
            ])->all(),
            $metric,
            $activated
        );
    }

    private function deltaReason(
        int $live,
        int $proposed,
        bool $legacyActivated,
        bool $proposedActivated,
        int $incomeEligibleDirects,
        int $proposedMetric,
        ?array $candidateMeta
    ): string {
        if ($live === $proposed) {
            return 'Proposed simulation matches live legacy entitlement.';
        }

        $basis = $candidateMeta['direct_qualification_basis'] ?? 'n/a';
        $tour = ! empty($candidateMeta['tour_only_qualifies_for_unlock']) ? 'yes' : 'no';

        if ($live > 0 && $proposed === 0 && ! $proposedActivated) {
            return sprintf(
                'ENTITLEMENT REDUCTION RISK: live legacy has %d level(s) from %d income-eligible direct(s) (activation ignored). Proposed bands require personal activation under basis=%s tour_only_unlock=%s — member is NOT personally activated for proposed filter, so proposed unlock is 0. Live posting unchanged until authorize.',
                $live,
                $incomeEligibleDirects,
                $basis,
                $tour
            );
        }

        if ($live > $proposed) {
            return sprintf(
                'ENTITLEMENT REDUCTION RISK: live %d → proposed %d (metric live=%d / proposed=%d, proposed_activated=%s, basis=%s). Live unchanged until authorize.',
                $live,
                $proposed,
                $incomeEligibleDirects,
                $proposedMetric,
                $proposedActivated ? 'yes' : 'no',
                $basis
            );
        }

        if ($live === 0 && $proposed > 0 && $proposedActivated) {
            return sprintf(
                'Proposed would grant %d level(s) (metric=%d, basis=%s, tour_only_unlock=%s). Requires publish + posting authorization; not live.',
                $proposed,
                $proposedMetric,
                $basis,
                $tour
            );
        }

        return sprintf(
            'Proposed %d vs live %d (legacy_activated=%s, proposed_activated=%s, basis=%s). Simulation only.',
            $proposed,
            $live,
            $legacyActivated ? 'yes' : 'no',
            $proposedActivated ? 'yes' : 'no',
            $basis
        );
    }
}
