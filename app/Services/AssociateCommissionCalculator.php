<?php

namespace App\Services;

use App\Models\Member;
use App\Models\PackageIncomeTerm;
use App\Models\PackagePurchase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only Associate Commission calculation foundation.
 *
 * Uses fixed USDT/day ranks from associate_v1 (never pool × percentage).
 * Reuses approved ReferralLevelEligibility unlock thresholds and the existing
 * package_income wait_days / 400-day window for provisional scheduling.
 * Never credits wallets, never writes accruals or ledger rows.
 */
class AssociateCommissionCalculator
{
    public const ENGINE = 'associate_commission_calculator_v1';

    public function __construct(
        private readonly AssociateCommissionStructure $structure,
        private readonly PackageIncomePolicy $policy,
        private readonly ReferralLevelEligibility $eligibility,
    ) {
    }

    /**
     * Dry-run preview for one purchase on one business date.
     *
     * @return array<string, mixed>
     */
    public function previewForPurchase(PackagePurchase $purchase, CarbonImmutable|string $businessDate): array
    {
        $date = $businessDate instanceof CarbonImmutable
            ? $businessDate->startOfDay()
            : CarbonImmutable::parse($businessDate)
                ->timezone((string) config('deltasupport.package_income.timezone', 'Asia/Kolkata'))
                ->startOfDay();

        $base = [
            'engine' => self::ENGINE,
            'mode' => 'dry_run',
            'mutates' => false,
            'ledger_mutations' => 0,
            'wallet_mutations' => 0,
            'posting_enabled' => $this->structure->postingEnabled(),
            'snapshot_on_approval' => $this->structure->snapshotOnApprovalEnabled(),
            'purchase_id' => (string) $purchase->id,
            'purchase_reference' => (string) $purchase->reference,
            'package_code' => (string) $purchase->package_code,
            'package_name' => (string) $purchase->package_name,
            'source_member_id' => (string) $purchase->member_id,
            'business_date' => $date->toDateString(),
            'status' => 'ok',
            'block_reason' => null,
            'unresolved' => [],
            'warnings' => [],
            'tier_code' => null,
            'version_code' => AssociateCommissionStructure::VERSION,
            'commission_days' => 400,
            'daily_total' => null,
            'first_accrual_date' => null,
            'last_accrual_date' => null,
            'within_window' => false,
            'legacy_overlap' => null,
            'lines' => [],
            'eligible_total' => '0.0000',
            'skipped_total' => '0.0000',
            'unallocated_pool' => null,
        ];

        if ($this->structure->postingEnabled()) {
            $base['warnings'][] = 'Associate posting flag is true, but this calculator never posts. Keep posting disabled until a separate approved poster exists.';
        }

        if ($purchase->status !== PackagePurchase::APPROVED || $purchase->reviewed_at === null) {
            return array_merge($base, [
                'status' => 'blocked',
                'block_reason' => 'Purchase is not approved (status='.$purchase->status.'). Cancelled/rejected purchases produce no associate commission.',
            ]);
        }

        $ranks = $this->resolveRanks($purchase);
        if ($ranks === null) {
            return array_merge($base, [
                'status' => 'blocked',
                'block_reason' => 'Package code '.$purchase->package_code.' is not mapped to associate_v1.',
            ]);
        }

        $first = $this->policy->firstAccrualDate($purchase);
        $last = $first === null ? null : $this->policy->lastAccrualDate($first, 400);
        $base['tier_code'] = $ranks['tier_code'];
        $base['version_code'] = $ranks['version_code'];
        $base['commission_days'] = $ranks['commission_days'];
        $base['daily_total'] = $ranks['daily_total'];
        $base['first_accrual_date'] = $first?->toDateString();
        $base['last_accrual_date'] = $last?->toDateString();
        $base['unresolved'][] = 'First eligible associate day currently reuses package_income.wait_days (legacy). An associate-specific start rule requires business approval if different.';
        $base['unresolved'][] = 'Unused daily pool for skipped/ineligible ranks is left unallocated; expire-vs-carry policy requires business approval.';
        $base['unresolved'][] = 'Refund/cancellation reversal of associate lines requires business approval.';
        $base['unresolved'][] = 'Member active/inactive status is not checked (same as legacy accrual). Refusing inactive recipients requires business approval.';
        $base['unresolved'][] = 'Unlock thresholds are evaluated live at calculation time (same as legacy). Freezing unlocks at approval/snapshot requires business approval.';

        $legacy = $this->policy->definition((string) $purchase->package_code);
        if ($legacy !== null) {
            $base['legacy_overlap'] = [
                'risk' => 'possible_double_payment_if_both_engines_active',
                'legacy_commission_pool' => $legacy['commission_pool'],
                'associate_daily_total' => $ranks['daily_total'],
                'note' => 'GOLD/PREMIUM have legacy commission_pool while associate ranks also map. Do not enable associate posting alongside legacy level accrual without an explicit exclusivity decision.',
            ];
            $base['warnings'][] = $base['legacy_overlap']['note'];
        }

        if ($first === null || ! $this->policy->withinWindow($date, $first, 400)) {
            $reason = $first === null
                ? 'Purchase has no approval date for accrual window.'
                : ($date->lessThan($first)
                    ? 'Business date is before first accrual day '.$first->toDateString().'.'
                    : 'Business date is after final accrual day '.$last?->toDateString().' (day 401+).');

            return array_merge($base, [
                'status' => 'outside_window',
                'block_reason' => $reason,
                'within_window' => false,
                'unallocated_pool' => $ranks['daily_total'],
            ]);
        }

        $base['within_window'] = true;
        $source = Member::query()->where('member_id', $purchase->member_id)->first();
        if ($source === null) {
            return array_merge($base, [
                'status' => 'blocked',
                'block_reason' => 'Source member '.$purchase->member_id.' has no member record.',
            ]);
        }

        $lines = $this->walkUpline($source, $purchase, $date, $ranks['ranks']);
        $eligibleTotal = '0.0000';
        $skippedTotal = '0.0000';
        foreach ($lines as $line) {
            if ($line['eligible']) {
                $eligibleTotal = bcadd($eligibleTotal, $line['amount'], 4);
            } else {
                $skippedTotal = bcadd($skippedTotal, $line['amount'], 4);
            }
        }

        $base['lines'] = $lines;
        $base['eligible_total'] = $eligibleTotal;
        $base['skipped_total'] = $skippedTotal;
        $base['unallocated_pool'] = bcsub($ranks['daily_total'], $eligibleTotal, 4);
        $base['status'] = 'dry_run_complete';

        return $base;
    }

    /**
     * Multi-scenario local simulation harness. Never mutates financial data.
     *
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    public function simulate(array $scenario): array
    {
        /** @var PackagePurchase $purchase */
        $purchase = $scenario['purchase'];
        $date = $scenario['business_date'];
        $result = $this->previewForPurchase($purchase, $date);
        $result['scenario'] = (string) ($scenario['name'] ?? 'unnamed');
        $result['expected_outcome'] = $scenario['expected_outcome'] ?? null;
        $result['ledger_before'] = $scenario['ledger_count_before'] ?? null;
        $result['ledger_after'] = $scenario['ledger_count_after'] ?? null;
        $result['mutates'] = false;
        $result['ledger_mutations'] = 0;
        $result['wallet_mutations'] = 0;

        return $result;
    }

    /**
     * @return array{tier_code: string, version_code: string, commission_days: int, daily_total: string, ranks: array<int, string>}|null
     */
    private function resolveRanks(PackagePurchase $purchase): ?array
    {
        if (Schema::hasColumn('package_income_terms', 'associate_rank_amounts')) {
            $term = PackageIncomeTerm::query()->where('package_purchase_id', $purchase->id)->first();
            if ($term !== null
                && is_array($term->associate_rank_amounts)
                && $term->associate_rank_amounts !== []
                && filled($term->associate_tier_code)) {
                return [
                    'tier_code' => (string) $term->associate_tier_code,
                    'version_code' => (string) ($term->associate_commission_version ?: AssociateCommissionStructure::VERSION),
                    'commission_days' => (int) ($term->associate_commission_days ?: 400),
                    'daily_total' => bcadd((string) ($term->associate_daily_total ?: '0'), '0', 4),
                    'ranks' => collect($term->associate_rank_amounts)
                        ->mapWithKeys(fn ($amount, $rank): array => [(int) $rank => bcadd((string) $amount, '0', 4)])
                        ->all(),
                ];
            }
        }

        return $this->structure->snapshotPayloadForPurchase($purchase);
    }

    /**
     * @param  array<int, string>  $ranks
     * @return list<array<string, mixed>>
     */
    private function walkUpline(Member $source, PackagePurchase $purchase, CarbonImmutable $date, array $ranks): array
    {
        $lines = [];
        $cursor = $source->sponsor_id;
        $seen = [$source->member_id => true];
        $recipients = [];

        for ($level = 1; $level <= ReferralLevelEligibility::MAX_LEVELS; $level++) {
            $amount = isset($ranks[$level]) ? bcadd((string) $ranks[$level], '0', 4) : '0.0000';
            $idempotencyKey = 'ASSOC|'.$purchase->id.'|'.$level.'|'.$date->toDateString();

            if (! filled($cursor)) {
                $lines[] = $this->line($purchase, $date, $level, null, $amount, false, 'Missing sponsor at rank '.$level.'.', $idempotencyKey);
                break;
            }

            if (isset($seen[$cursor])) {
                $lines[] = $this->line($purchase, $date, $level, $cursor, $amount, false, 'Circular or duplicate sponsor chain at '.$cursor.'.', $idempotencyKey);
                break;
            }
            $seen[$cursor] = true;

            if ($cursor === $this->policy->adminRootId()) {
                $lines[] = $this->line($purchase, $date, $level, $cursor, $amount, false, 'Admin root receives no associate commission.', $idempotencyKey);
                break;
            }

            $sponsor = Member::query()->where('member_id', $cursor)->first();
            if ($sponsor === null) {
                $lines[] = $this->line($purchase, $date, $level, $cursor, $amount, false, 'Sponsor '.$cursor.' has no member record.', $idempotencyKey);
                break;
            }

            if ($sponsor->member_id === $source->member_id) {
                $lines[] = $this->line($purchase, $date, $level, $sponsor->member_id, $amount, false, 'Self-commission blocked.', $idempotencyKey);
                break;
            }

            $next = $sponsor->sponsor_id;
            $unlocked = $this->eligibility->forMember($sponsor->member_id)['unlocked_levels'];
            $recipientEligible = $recipients[$sponsor->member_id] ??= $this->recipientEligible($sponsor->member_id);
            $eligible = $recipientEligible && $unlocked >= $level;

            $reason = match (true) {
                ! $recipientEligible => 'Recipient '.$sponsor->member_id.' has no qualifying purchase (inactive/unqualified for level income).',
                $unlocked < $level => 'Recipient '.$sponsor->member_id.' has only '.$unlocked.' unlocked levels (needs '.$level.').',
                default => 'Eligible under existing ReferralLevelEligibility thresholds evaluated at calculation time.',
            };

            $lines[] = $this->line(
                $purchase,
                $date,
                $level,
                $sponsor->member_id,
                $amount,
                $eligible,
                $reason,
                $idempotencyKey,
                $unlocked,
                $recipientEligible
            );

            $cursor = $next;
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function line(
        PackagePurchase $purchase,
        CarbonImmutable $date,
        int $rank,
        ?string $recipientId,
        string $amount,
        bool $eligible,
        string $reason,
        string $idempotencyKey,
        ?int $unlockedLevels = null,
        ?bool $recipientEligible = null,
    ): array {
        return [
            'purchase_id' => (string) $purchase->id,
            'package_code' => (string) $purchase->package_code,
            'business_date' => $date->toDateString(),
            'rank' => $rank,
            'intended_recipient' => $recipientId,
            'eligible' => $eligible,
            'amount' => $amount,
            'skip_reason' => $eligible ? null : $reason,
            'eligibility_reason' => $reason,
            'unlocked_levels' => $unlockedLevels,
            'recipient_qualifying' => $recipientEligible,
            'idempotency_key' => $idempotencyKey,
            'ledger_mutation' => false,
            'wallet_mutation' => false,
        ];
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
}
