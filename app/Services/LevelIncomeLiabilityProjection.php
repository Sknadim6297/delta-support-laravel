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
 * Read-only Level Income commission liability projection.
 *
 * Compares live legacy unlock/rates vs a candidate draft (Option B style) under a
 * simulated applicability policy for one future business date.
 * Never writes wallets, accruals, purchases, authorization, or scheduler state.
 * Does not activate Associate Commission. Does not alter ROI planning.
 */
class LevelIncomeLiabilityProjection
{
    public const DEFAULT_VERSION_CODE = 'proposed_activation_unlock_v1';

    public function __construct(
        private readonly PackageIncomePolicy $policy,
        private readonly PackageIncomeActivationGate $activation,
        private readonly MemberPaymentControlService $paymentControls,
        private readonly LevelIncomeRuleResolver $resolver,
        private readonly LevelIncomeDirectQualification $qualification,
        private readonly LevelIncomeUnlockGrandfathering $grandfathering,
        private readonly ReferralLevelEligibility $eligibility,
        private readonly PackageIncomeAccrual $accrual,
        private readonly LevelIncomeUnlockImpactAnalyzer $unlockAnalyzer,
        private readonly AssociateCommissionStructure $associate,
    ) {
    }

    /**
     * @param  array{
     *   version_code?: string,
     *   applicability?: string,
     *   effective_from?: string|null,
     *   include_roi_check?: bool,
     *   detail_limit?: int
     * }  $options
     * @return array<string, mixed>
     */
    public function project(string $businessDate, array $options = []): array
    {
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $date = CarbonImmutable::parse($businessDate, $timezone)->startOfDay();
        $versionCode = (string) ($options['version_code'] ?? self::DEFAULT_VERSION_CODE);
        $candidate = $this->loadCandidate($versionCode);
        $applicability = (string) ($options['applicability']
            ?? $candidate?->applicability
            ?? LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE);
        if (! in_array($applicability, [
            LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
        ], true)) {
            $applicability = LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE;
        }
        $effectiveFrom = (string) ($options['effective_from']
            ?? optional($candidate?->effective_from)?->toDateString()
            ?? $date->toDateString());
        $includeRoiCheck = (bool) ($options['include_roi_check'] ?? true);
        $detailLimit = max(20, min(5000, (int) ($options['detail_limit'] ?? 200)));

        $assumptions = [
            'read_only' => true,
            'mutates' => false,
            'no_historical_backfill' => true,
            'associate_commission_posting_enabled' => $this->associate->postingEnabled(),
            'associate_commission_must_remain_off' => true,
            'candidate_posting_authorized' => (bool) ($candidate?->posting_authorized ?? false),
            'simulation_treats_candidate_as_if_authorized_under_applicability' => true,
            'applicability' => $applicability,
            'effective_from' => $effectiveFrom,
            'business_date' => $date->toDateString(),
            'unlock_metric_frozen_as_of_business_date' => true,
            'remaining_liability_assumes_constant_unlock_and_controls' => true,
            'tour_only_bv_separate_from_commission_generation' => true,
            'payment_controls_respected' => true,
            'package_terms_immutable' => true,
            'waiting_period_and_commission_duration_respected' => true,
            'grandfathering_formula' => 'protected = max(proposed, min(snapshot_legacy, current_legacy))',
            'roi_path_unchanged' => true,
        ];

        if ($candidate === null) {
            return [
                'read_only' => true,
                'mutates' => false,
                'error' => 'Candidate version not found: '.$versionCode,
                'assumptions' => $assumptions,
            ];
        }

        $candidate->loadMissing(['bands', 'rates']);
        $rateChartProposed = $this->rateChartForVersion($candidate);
        $rateChartLegacy = CommissionPlan::RATE_BY_LEVEL;

        $legacyLines = [];
        $proposedLines = [];
        $purchaseRows = [];
        $skipped = [];
        $recipients = [];
        $roiLive = [];

        if ($includeRoiCheck) {
            // Live ROI plan only — proposed path must not diverge ROI.
            $livePlan = $this->accrual->previewReal($date->toDateString());
            $roiLive = $livePlan['roi'] ?? [];
        }

        $purchases = PackagePurchase::query()
            ->where('status', PackagePurchase::APPROVED)
            ->whereNotNull('reviewed_at')
            ->orderBy('id')
            ->get();
        $packages = Package::query()
            ->whereIn('id', $purchases->pluck('package_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        foreach ($purchases as $purchase) {
            $package = $packages->get($purchase->package_id);
            $row = $this->projectPurchase(
                $purchase,
                $package,
                $date,
                $candidate,
                $applicability,
                $effectiveFrom,
                $rateChartLegacy,
                $rateChartProposed,
                $recipients,
                $skipped
            );
            if ($row === null) {
                continue;
            }
            $purchaseRows[] = $row;
            foreach ($row['legacy_lines'] as $line) {
                $legacyLines[] = $line;
            }
            foreach ($row['proposed_lines'] as $line) {
                $proposedLines[] = $line;
            }
        }

        $legacyTotal = $this->sumAmounts($legacyLines);
        $proposedTotal = $this->sumAmounts($proposedLines);
        $additionalDaily = bcsub($proposedTotal, $legacyTotal, 4);

        $memberDiffs = $this->aggregateByMember($legacyLines, $proposedLines);
        $levelDiffs = $this->aggregateByLevel($legacyLines, $proposedLines);
        $purchaseDiffs = array_values(array_filter(
            $purchaseRows,
            static fn (array $row): bool => bccomp((string) $row['additional_daily'], '0', 4) !== 0
                || bccomp((string) $row['legacy_daily'], '0', 4) !== 0
                || bccomp((string) $row['proposed_daily'], '0', 4) !== 0
        ));

        $remainingAdditional = '0.0000';
        $remainingLegacy = '0.0000';
        $remainingProposed = '0.0000';
        foreach ($purchaseRows as $row) {
            $days = (int) $row['remaining_commission_days'];
            if ($days < 1) {
                continue;
            }
            $remainingAdditional = bcadd(
                $remainingAdditional,
                bcmul((string) $row['additional_daily'], (string) $days, 4),
                4
            );
            $remainingLegacy = bcadd(
                $remainingLegacy,
                bcmul((string) $row['legacy_daily'], (string) $days, 4),
                4
            );
            $remainingProposed = bcadd(
                $remainingProposed,
                bcmul((string) $row['proposed_daily'], (string) $days, 4),
                4
            );
        }

        $roiTotal = $this->sumAmounts($roiLive);
        $unlockImpact = $this->unlockAnalyzer->analyzeMembers(
            Member::query()->orderBy('id')->pluck('member_id')->all(),
            $candidate
        );
        $tourBv = $this->unlockAnalyzer->tourOnlyBusinessVolumeAudit();

        return [
            'read_only' => true,
            'mutates' => false,
            'generated_at' => now()->toIso8601String(),
            'option' => 'B',
            'label' => 'Combined Level Unlock financial impact preview (existing_prospective simulation)',
            'assumptions' => $assumptions,
            'candidate' => [
                'version_code' => $candidate->version_code,
                'status' => $candidate->status,
                'posting_authorized' => (bool) $candidate->posting_authorized,
                'direct_qualification_basis' => $this->qualification->normalizeBasis($candidate->direct_qualification_basis ?? null),
                'tour_only_qualifies_for_unlock' => (bool) ($candidate->tour_only_qualifies_for_unlock ?? false),
                'combined_joining_enabled' => (bool) ($candidate->combined_joining_enabled ?? false),
                'combined_purchase_enabled' => (bool) ($candidate->combined_purchase_enabled ?? false),
                'combined_purchase_scope' => $candidate->combined_purchase_scope,
                'combined_repeat_purchase_mode' => $candidate->combined_repeat_purchase_mode,
                'combined_double_credit_policy' => $candidate->combined_double_credit_policy,
                'combined_joining_unit_value' => (int) ($candidate->combined_joining_unit_value ?? 1),
                'combined_purchase_unit_value' => (int) ($candidate->combined_purchase_unit_value ?? 1),
                'grandfather_existing_unlock' => (bool) ($candidate->grandfather_existing_unlock ?? false),
                'applicability_simulated' => $applicability,
                'effective_from_simulated' => $effectiveFrom,
            ],
            'totals' => [
                'registered_members' => Member::query()->count(),
                'approved_source_purchases_scanned' => $purchases->count(),
                'commission_eligible_purchases_in_window' => count($purchaseRows),
                'legacy_commission_daily' => $legacyTotal,
                'proposed_commission_daily' => $proposedTotal,
                'additional_daily_liability' => $additionalDaily,
                'remaining_projected_legacy_liability' => $remainingLegacy,
                'remaining_projected_proposed_liability' => $remainingProposed,
                'remaining_projected_additional_liability' => $remainingAdditional,
                'roi_daily_unchanged_check_total' => $roiTotal,
                'roi_line_count' => count($roiLive),
                'associate_commission_posting_enabled' => $this->associate->postingEnabled(),
            ],
            'unlock_summary' => $unlockImpact['summary'],
            'tour_only_business_volume' => [
                'purchase_count' => $tourBv['purchase_count'],
                'total_usdt' => $tourBv['total_usdt'],
                'counted_once' => $tourBv['counted_once'],
                'note' => $tourBv['note'],
            ],
            'member_differences' => array_slice($memberDiffs, 0, $detailLimit),
            'purchase_differences' => array_slice($purchaseDiffs, 0, $detailLimit),
            'level_differences' => $levelDiffs,
            'skipped_sample' => array_slice($skipped, 0, 40),
            'safety' => [
                'wallet_writes' => 0,
                'accrual_writes' => 0,
                'authorization_changed' => false,
                'scheduler_changed' => false,
                'associate_engine_activated' => false,
                'historical_backfill' => false,
            ],
        ];
    }

    private function loadCandidate(string $versionCode): ?LevelIncomeRuleVersion
    {
        if (! Schema::hasTable('level_income_rule_versions')) {
            return null;
        }

        return LevelIncomeRuleVersion::query()
            ->with(['bands', 'rates'])
            ->where('version_code', $versionCode)
            ->first();
    }

    /**
     * @return array<int, string>
     */
    private function rateChartForVersion(LevelIncomeRuleVersion $version): array
    {
        $rates = [];
        foreach ($version->rates as $row) {
            $rates[(int) $row->level] = bcadd((string) $row->percentage, '0', 4);
        }

        return $rates !== [] ? $rates : CommissionPlan::RATE_BY_LEVEL;
    }

    /**
     * @param  array<int, string>  $legacyRates
     * @param  array<int, string>  $proposedRates
     * @param  array<string, bool>  $recipients
     * @param  list<string>  $skipped
     * @return array<string, mixed>|null
     */
    private function projectPurchase(
        PackagePurchase $purchase,
        ?Package $package,
        CarbonImmutable $date,
        LevelIncomeRuleVersion $candidate,
        string $applicability,
        string $effectiveFrom,
        array $legacyRates,
        array $proposedRates,
        array &$recipients,
        array &$skipped
    ): ?array {
        $definition = $this->policy->definitionForPurchase($purchase);
        $originalFirst = $this->activation->originalFirstAccrualDate($purchase, $this->policy);
        if ($definition === null || $originalFirst === null) {
            return null;
        }

        if ($this->activation->requiresActivation((string) $purchase->package_code)
            && $this->activation->activationBusinessDate((string) $purchase->package_code) === null) {
            $skipped[] = $purchase->package_code.' income activation date is missing (projection skipped).';

            return null;
        }

        // Tour posting authorization gate: projection still evaluates commission eligibility
        // only for level-eligible sources; Tour Only packages remain non-generators.
        $allowedFirst = $this->activation->firstAllowedIncomeDate($purchase, $this->policy);
        if ($allowedFirst === null || $date->lessThan($allowedFirst)) {
            return null;
        }

        if (! $this->policy->levelSourceEligible($purchase, $package)) {
            return null;
        }

        $commissionDays = (int) $definition['commission_days'];
        if (! $this->policy->withinWindow($date, $originalFirst, $commissionDays)) {
            return null;
        }

        $source = Member::query()->where('member_id', $purchase->member_id)->first();
        if ($source === null) {
            $skipped[] = $purchase->reference.' has no source member record.';

            return null;
        }

        $pool = (string) $definition['commission_pool'];
        $legacyLines = $this->walkUpline(
            $source,
            $purchase,
            $pool,
            $date,
            false,
            $candidate,
            $applicability,
            $effectiveFrom,
            $legacyRates,
            $recipients,
            $skipped
        );
        $proposedLines = $this->walkUpline(
            $source,
            $purchase,
            $pool,
            $date,
            true,
            $candidate,
            $applicability,
            $effectiveFrom,
            $proposedRates,
            $recipients,
            $skipped
        );

        $legacyDaily = $this->sumAmounts($legacyLines);
        $proposedDaily = $this->sumAmounts($proposedLines);
        $last = $this->policy->lastAccrualDate($originalFirst, $commissionDays);
        $remainingDays = 0;
        if ($last !== null && $date->lessThanOrEqualTo($last)) {
            $remainingDays = $date->diffInDays($last) + 1;
        }

        return [
            'purchase_id' => (int) $purchase->id,
            'reference' => $purchase->reference,
            'source_member_id' => $purchase->member_id,
            'package_code' => $purchase->package_code,
            'pool' => $pool,
            'commission_days' => $commissionDays,
            'first_accrual_date' => $originalFirst->toDateString(),
            'last_accrual_date' => $last?->toDateString(),
            'remaining_commission_days' => $remainingDays,
            'candidate_applies' => $this->candidateApplies($purchase, $date->toDateString(), $applicability, $effectiveFrom),
            'legacy_daily' => $legacyDaily,
            'proposed_daily' => $proposedDaily,
            'additional_daily' => bcsub($proposedDaily, $legacyDaily, 4),
            'legacy_lines' => $legacyLines,
            'proposed_lines' => $proposedLines,
        ];
    }

    /**
     * @param  array<int, string>  $rates
     * @param  array<string, bool>  $recipients
     * @param  list<string>  $skipped
     * @return list<array<string, mixed>>
     */
    private function walkUpline(
        Member $source,
        PackagePurchase $purchase,
        string $pool,
        CarbonImmutable $date,
        bool $useProposed,
        LevelIncomeRuleVersion $candidate,
        string $applicability,
        string $effectiveFrom,
        array $rates,
        array &$recipients,
        array &$skipped
    ): array {
        $lines = [];
        $cursor = $source->sponsor_id;
        $seen = [$source->member_id => true];
        $businessDate = $date->toDateString();
        $applies = $useProposed && $this->candidateApplies($purchase, $businessDate, $applicability, $effectiveFrom);

        for ($level = 1; $level <= ReferralLevelEligibility::MAX_LEVELS; $level++) {
            if (! filled($cursor) || isset($seen[$cursor])) {
                break;
            }
            $seen[$cursor] = true;
            if ($cursor === $this->policy->adminRootId()) {
                break;
            }
            $sponsor = Member::query()->where('member_id', $cursor)->first();
            if ($sponsor === null) {
                $skipped[] = 'Sponsor '.$cursor.' missing for purchase '.$purchase->reference;
                break;
            }
            $next = $sponsor->sponsor_id;

            $unlocked = $applies
                ? $this->proposedUnlocked($sponsor->member_id, $candidate)
                : $this->legacyUnlocked($sponsor->member_id);

            $eligible = $recipients[$sponsor->member_id] ??= $this->recipientEligible($sponsor->member_id);
            $paymentAllowed = $this->paymentControls->allowsLevel($sponsor->member_id, $businessDate);

            if ($eligible && $paymentAllowed && $unlocked >= $level && $sponsor->member_id !== $this->policy->adminRootId()) {
                $rate = $rates[$level] ?? CommissionPlan::RATE_BY_LEVEL[$level] ?? null;
                $amount = $this->policy->levelAmount($pool, $level, $rate);
                if ($amount !== null && bccomp($amount, '0', 4) === 1) {
                    $lines[] = [
                        'purchase_id' => (int) $purchase->id,
                        'member_id' => $sponsor->member_id,
                        'from_member_id' => $source->member_id,
                        'level' => $level,
                        'rate' => $rate,
                        'amount' => $amount,
                        'unlocked_levels' => $unlocked,
                        'mode' => $applies ? 'proposed' : 'legacy',
                    ];
                }
            }

            $cursor = $next;
        }

        return $lines;
    }

    private function candidateApplies(
        PackagePurchase $purchase,
        string $businessDate,
        string $applicability,
        string $effectiveFrom
    ): bool {
        if ($applicability === LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY) {
            $approval = $this->policy->approvalDate($purchase)?->toDateString();

            return $approval !== null && $approval >= $effectiveFrom;
        }

        // existing_prospective
        return $businessDate >= $effectiveFrom;
    }

    private function legacyUnlocked(string $memberId): int
    {
        $count = $this->eligibility->qualifyingDirectCount($memberId);

        return $this->resolver->legacyLevelsForCount($count);
    }

    private function proposedUnlocked(string $memberId, LevelIncomeRuleVersion $candidate): int
    {
        $breakdown = $this->qualification->breakdownForVersion($memberId, $candidate);
        $metric = (int) ($breakdown['total_metric'] ?? 0);
        $activated = $this->qualification->isPersonallyActivatedForVersion($memberId, $candidate);
        $candidate->loadMissing('bands');
        $raw = $this->resolver->simulateUnlockedLevels(
            $candidate->bands->map(fn ($b): array => [
                'start_level' => (int) $b->start_level,
                'end_level' => (int) $b->end_level,
                'min_qualifying_directs' => (int) $b->min_qualifying_directs,
                'requires_personal_activation' => (bool) $b->requires_personal_activation,
            ])->all(),
            $metric,
            $activated
        );

        return $this->grandfathering->apply($memberId, $candidate, $raw);
    }

    private function recipientEligible(string $memberId): bool
    {
        if ($memberId === $this->policy->adminRootId()) {
            return false;
        }

        return Member::query()
            ->where('member_id', $memberId)
            ->whereExists(function ($query): void {
                $this->eligibility->qualifyingPurchaseConstraint($query);
            })
            ->exists();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function sumAmounts(array $lines): string
    {
        $total = '0.0000';
        foreach ($lines as $line) {
            $total = bcadd($total, (string) ($line['amount'] ?? '0'), 4);
        }

        return $total;
    }

    /**
     * @param  list<array<string, mixed>>  $legacy
     * @param  list<array<string, mixed>>  $proposed
     * @return list<array<string, mixed>>
     */
    private function aggregateByMember(array $legacy, array $proposed): array
    {
        $map = [];
        foreach ($legacy as $line) {
            $id = (string) $line['member_id'];
            $map[$id] ??= [
                'member_id' => $id,
                'legacy_daily' => '0.0000',
                'proposed_daily' => '0.0000',
            ];
            $map[$id]['legacy_daily'] = bcadd($map[$id]['legacy_daily'], (string) $line['amount'], 4);
        }
        foreach ($proposed as $line) {
            $id = (string) $line['member_id'];
            $map[$id] ??= [
                'member_id' => $id,
                'legacy_daily' => '0.0000',
                'proposed_daily' => '0.0000',
            ];
            $map[$id]['proposed_daily'] = bcadd($map[$id]['proposed_daily'], (string) $line['amount'], 4);
        }

        $rows = [];
        foreach ($map as $row) {
            $row['additional_daily'] = bcsub($row['proposed_daily'], $row['legacy_daily'], 4);
            $rows[] = $row;
        }
        usort($rows, static fn (array $a, array $b): int => bccomp($b['additional_daily'], $a['additional_daily'], 4));

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $legacy
     * @param  list<array<string, mixed>>  $proposed
     * @return list<array<string, mixed>>
     */
    private function aggregateByLevel(array $legacy, array $proposed): array
    {
        $map = [];
        for ($level = 1; $level <= ReferralLevelEligibility::MAX_LEVELS; $level++) {
            $map[$level] = [
                'level' => $level,
                'legacy_daily' => '0.0000',
                'proposed_daily' => '0.0000',
                'legacy_line_count' => 0,
                'proposed_line_count' => 0,
            ];
        }
        foreach ($legacy as $line) {
            $level = (int) $line['level'];
            $map[$level]['legacy_daily'] = bcadd($map[$level]['legacy_daily'], (string) $line['amount'], 4);
            $map[$level]['legacy_line_count']++;
        }
        foreach ($proposed as $line) {
            $level = (int) $line['level'];
            $map[$level]['proposed_daily'] = bcadd($map[$level]['proposed_daily'], (string) $line['amount'], 4);
            $map[$level]['proposed_line_count']++;
        }

        $rows = [];
        foreach ($map as $row) {
            $row['additional_daily'] = bcsub($row['proposed_daily'], $row['legacy_daily'], 4);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Assert no write statements occurred during a callback (for tests).
     *
     * @template T
     * @param  callable(): T  $callback
     * @return array{result: T, write_count: int}
     */
    public function runAssertingNoWrites(callable $callback): array
    {
        $writes = 0;
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace)/i', $query->sql)) {
                $writes++;
            }
        });

        $result = $callback();

        return ['result' => $result, 'write_count' => $writes];
    }
}
