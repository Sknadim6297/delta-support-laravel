<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Read-only member preview for one Asia/Kolkata business date.
 * Payable amounts come from PackageIncomeAccrual::preview(), which does not write.
 */
class PackageIncomeCalculationPreview
{
    public function __construct(
        private readonly PackageIncomePolicy $policy,
        private readonly PackageIncomeAccrual $accrual,
        private readonly ReferralLevelEligibility $eligibility,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forDate(?string $businessDate): array
    {
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $empty = $this->emptyTotals();
        if ($businessDate === null || $businessDate === '') {
            $businessDate = CarbonImmutable::now($timezone)->toDateString();
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $businessDate)) {
            return $this->invalid($businessDate, $empty);
        }
        [$year, $month, $day] = array_map('intval', explode('-', $businessDate));
        if (! checkdate($month, $day, $year)) {
            return $this->invalid($businessDate, $empty);
        }

        $date = CarbonImmutable::parse($businessDate, $timezone)->startOfDay();
        $plan = $this->accrual->preview($date->toDateString());
        $roiByPurchase = collect($plan['roi'])->keyBy('purchase_id');
        $commissionByPurchase = collect($plan['commissions'])->groupBy('purchase_id');
        $members = Member::query()->orderBy('member_id')->get()->keyBy('member_id');
        $purchases = PackagePurchase::query()->orderBy('id')->get()->groupBy('member_id');
        $packages = Package::query()->whereIn('id', PackagePurchase::query()->pluck('package_id')->filter()->all())->get()->keyBy('id');
        $roiCredited = $this->sums(PackageRoiAccrual::query(), 'amount');
        $levelCredited = $this->sums(PackageLevelAccrual::query(), 'amount');
        $roiOnDate = PackageRoiAccrual::query()
            ->whereDate('business_date', $date->toDateString())
            ->pluck('package_purchase_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->flip();
        $levelOnDate = PackageLevelAccrual::query()
            ->whereDate('business_date', $date->toDateString())
            ->get()
            ->mapWithKeys(fn (PackageLevelAccrual $row): array => [$row->package_purchase_id.'|'.$row->member_id => true]);

        $rows = [];
        $ids = $members->keys()->merge($purchases->keys())->unique()->sort()->values();
        foreach ($ids as $memberId) {
            $member = $members->get($memberId);
            $memberPurchases = $purchases->get($memberId, collect());
            if ($memberPurchases->isEmpty()) {
                $rows[] = $this->emptyMember($member, (string) $memberId, $date);

                continue;
            }
            foreach ($memberPurchases as $purchase) {
                $rows[] = $this->purchaseRow(
                    $member,
                    $purchase,
                    $packages->get($purchase->package_id),
                    $date,
                    $roiByPurchase->get((string) $purchase->id),
                    $commissionByPurchase->get((string) $purchase->id, collect()),
                    $this->money($roiCredited[(string) $purchase->id] ?? '0'),
                    $this->money($levelCredited[(string) $purchase->id] ?? '0'),
                    $roiOnDate->has((string) $purchase->id),
                    $levelOnDate
                );
            }
        }

        return [
            'valid' => true,
            'date' => $date->toDateString(),
            'timezone' => $timezone,
            'error' => null,
            'rows' => $rows,
            'recipients' => $this->recipients($plan['commissions'], $members),
            'totals' => $this->totals($rows, $plan),
            'stored_notice' => 'Calculated for this page view. These figures are not stored credits and are not income history.',
        ];
    }

    /**
     * @param  array<string, string>|null  $roiPlan
     * @param  Collection<int, array<string, string>>  $commissionPlan
     * @param  Collection<string, bool>  $levelOnDate
     * @return array<string, mixed>
     */
    private function purchaseRow(?Member $member, PackagePurchase $purchase, ?Package $package, CarbonImmutable $date, ?array $roiPlan, Collection $commissionPlan, string $roiCredited, string $levelCredited, bool $roiOnDate, Collection $levelOnDate): array
    {
        $definition = $this->policy->definition((string) $purchase->package_code);
        $first = $this->policy->firstAccrualDate($purchase);
        $roiDays = $definition['roi_days'] ?? 0;
        $commissionDays = $definition['commission_days'] ?? null;
        $roiEligible = $this->policy->roiEligible($purchase, $package);
        $levelEligible = $this->policy->levelSourceEligible($purchase, $package);
        $lastRoi = ($roiEligible && $first) ? $this->policy->lastAccrualDate($first, $roiDays) : null;
        $lastCommission = ($levelEligible && $first && $commissionDays) ? $this->policy->lastAccrualDate($first, (int) $commissionDays) : null;
        $roiWindow = $roiEligible && $first && $lastRoi && $this->policy->withinWindow($date, $first, $roiDays);
        $commissionWindow = $levelEligible && $first && $lastCommission && $commissionDays && $this->policy->withinWindow($date, $first, (int) $commissionDays);
        $labels = [];
        $reasons = [];

        if ($definition === null || ! $package) {
            $labels[] = 'Blocked by missing package';
            $reasons[] = $definition === null
                ? 'No income definition is stored for this package code.'
                : 'The package record linked to this purchase is missing.';
        } elseif (! $roiEligible && ! $levelEligible && ($purchase->status !== PackagePurchase::APPROVED || $purchase->reviewed_at === null)) {
            $labels[] = 'Not yet eligible';
            $reasons[] = 'The purchase is not approved, so it earns nothing on this date.';
        } elseif (! $roiEligible && ! $levelEligible) {
            $labels[] = 'Blocked by missing package';
            $reasons[] = 'This package is not eligible for ROI or level commission.';
        }

        if (($roiEligible || $levelEligible) && $definition !== null && $first === null && $purchase->status === PackagePurchase::APPROVED) {
            $labels[] = 'Not yet eligible';
            $reasons[] = 'An approval business date is not stored, so no income date exists.';
        } elseif (($roiEligible || $levelEligible) && $first && $date->lessThan($first)) {
            $labels[] = 'Not yet eligible';
            $reasons[] = 'The first income date is '.$first->toDateString().'.';
        }

        if ($roiWindow && $roiPlan !== null) {
            $labels[] = 'Eligible for ROI';
        } elseif ($roiEligible && $first && $lastRoi && $date->greaterThan($lastRoi)) {
            $labels[] = 'Past the payout duration';
            $reasons[] = 'ROI ended on '.$lastRoi->toDateString().'.';
        }
        if ($roiOnDate) {
            $labels[] = 'Already credited';
            $reasons[] = 'An ROI accrual row is already stored for this date.';
        }

        $levels = [];
        if ($commissionWindow) {
            $levels = $this->levels($member, $purchase, (string) $definition['commission_pool'], $commissionPlan);
            $payable = $this->sum(array_column($levels, 'payable_amount'));
            if (bccomp($payable, '0', 4) === 1) {
                $labels[] = 'Eligible for commission';
            }
            foreach ($levels as $level) {
                if ($level['status'] !== 'Eligible for commission' && ! in_array($level['status'], $labels, true)) {
                    $labels[] = $level['status'];
                }
                if ($level['reason'] !== '') {
                    $reasons[] = 'Level '.$level['level'].': '.$level['reason'];
                }
            }
            if ($levels === []) {
                $labels[] = 'Blocked by ineligible sponsor';
                $reasons[] = 'No sponsor level was available.';
            }
        } elseif ($levelEligible && $first && $lastCommission && $date->greaterThan($lastCommission)) {
            $labels[] = 'Past the payout duration';
            $reasons[] = 'Commission ended on '.$lastCommission->toDateString().'.';
        }

        $pendingCommission = '0.0000';
        foreach ($commissionPlan as $row) {
            if (! $levelOnDate->has($purchase->id.'|'.$row['member_id'])) {
                $pendingCommission = bcadd($pendingCommission, $row['amount'], 4);
            } else {
                $labels[] = 'Already credited';
                $reasons[] = 'A level accrual row is already stored for '.$row['member_id'].' on this date.';
            }
        }

        $dailyRoi = $roiEligible ? $definition['roi_daily'] : '0.0000';
        $pendingRoi = ($roiPlan !== null && ! $roiOnDate) ? $roiPlan['amount'] : '0.0000';

        return [
            'member_id' => (string) $purchase->member_id,
            'member_name' => $member->member_name ?? 'Not stored',
            'sponsor_id' => $member->sponsor_id ?: 'Not stored',
            'package_name' => (string) $purchase->package_name,
            'package_code' => (string) $purchase->package_code,
            'purchase_amount' => bcadd((string) $purchase->price_usd, '0', 4),
            'currency' => (string) ($purchase->denomination ?: 'Not stored'),
            'approval_at' => $purchase->reviewed_at?->timezone(config('deltasupport.package_income.timezone'))->format('d M Y, H:i') ?? 'Not stored',
            'first_roi_date' => ($roiEligible && $first) ? $first->toDateString() : 'Not stored',
            'last_roi_date' => $lastRoi?->toDateString() ?? 'Not stored',
            'first_commission_date' => ($levelEligible && $first) ? $first->toDateString() : 'Not stored',
            'last_commission_date' => $lastCommission?->toDateString() ?? 'Not stored',
            'business_date' => $date->toDateString(),
            'daily_roi' => $dailyRoi,
            'theoretical_pool' => $levelEligible ? $definition['commission_pool'] : '0.0000',
            'payable_commission' => $this->sum($commissionPlan->pluck('amount')->all()),
            'credited_roi' => $roiCredited,
            'credited_commission' => $levelCredited,
            'pending_roi' => $pendingRoi,
            'pending_commission' => $pendingCommission,
            'labels' => array_values(array_unique($labels)),
            'reasons' => array_values(array_unique($reasons)),
            'levels' => $levels,
        ];
    }

    /**
     * @param  Collection<int, array<string, string>>  $commissionPlan
     * @return list<array<string, string>>
     */
    private function levels(?Member $source, PackagePurchase $purchase, string $pool, Collection $commissionPlan): array
    {
        $planned = [];
        foreach ($commissionPlan as $row) {
            $planned[$row['level'].'|'.$row['member_id']] = $row;
        }
        if (! $source) {
            return [[
                'level' => '1',
                'recipient_id' => 'Not stored',
                'unlocked_levels' => '0',
                'rate_amount' => '0.0000',
                'payable_amount' => '0.0000',
                'status' => 'Blocked by ineligible sponsor',
                'reason' => 'The source member record is missing.',
            ]];
        }

        $rows = [];
        $cursor = $source->sponsor_id;
        $seen = [$source->member_id => true];
        for ($level = 1; $level <= ReferralLevelEligibility::MAX_LEVELS; $level++) {
            $rateAmount = $this->policy->levelAmount($pool, $level) ?? '0.0000';
            if (! filled($cursor) || isset($seen[$cursor])) {
                $rows[] = $this->levelRow((string) $level, 'Not stored', '0', $rateAmount, '0.0000', 'Blocked by ineligible sponsor', 'The sponsor chain stopped before this level.');
                break;
            }
            $seen[$cursor] = true;
            if ($cursor === $this->policy->adminRootId()) {
                $rows[] = $this->levelRow((string) $level, $cursor, '0', $rateAmount, '0.0000', 'Blocked by ineligible sponsor', 'Admin root receives no commission.');
                break;
            }
            $sponsor = Member::query()->where('member_id', $cursor)->first();
            if (! $sponsor) {
                $rows[] = $this->levelRow((string) $level, (string) $cursor, '0', $rateAmount, '0.0000', 'Blocked by ineligible sponsor', 'This sponsor has no member record.');
                break;
            }
            $unlocked = (string) $this->eligibility->forMember($sponsor->member_id)['unlocked_levels'];
            $plannedRow = $planned[$level.'|'.$sponsor->member_id] ?? null;
            if ($plannedRow) {
                $rows[] = $this->levelRow((string) $level, $sponsor->member_id, $unlocked, $rateAmount, $plannedRow['amount'], 'Eligible for commission', 'Payable from the accrual preview.');
            } elseif (! $this->recipientEligible($sponsor->member_id)) {
                $rows[] = $this->levelRow((string) $level, $sponsor->member_id, $unlocked, $rateAmount, '0.0000', 'Blocked by ineligible sponsor', 'This sponsor has no approved eligible package.');
            } elseif ((int) $unlocked < $level) {
                $rows[] = $this->levelRow((string) $level, $sponsor->member_id, $unlocked, $rateAmount, '0.0000', 'Blocked by locked levels', 'Unlocked levels are '.$unlocked.'.');
            } else {
                $rows[] = $this->levelRow((string) $level, $sponsor->member_id, $unlocked, $rateAmount, '0.0000', 'Blocked by ineligible sponsor', 'The accrual preview did not include this level.');
            }
            $cursor = $sponsor->sponsor_id;
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function levelRow(string $level, string $recipient, string $unlocked, string $rateAmount, string $payable, string $status, string $reason): array
    {
        return [
            'level' => $level,
            'recipient_id' => $recipient,
            'unlocked_levels' => $unlocked,
            'rate_amount' => $rateAmount,
            'payable_amount' => $payable,
            'status' => $status,
            'reason' => $reason,
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

    /**
     * @param  list<array<string, string>>  $commissions
     * @param  Collection<string, Member>  $members
     * @return list<array<string, string>>
     */
    private function recipients(array $commissions, Collection $members): array
    {
        $grouped = [];
        foreach ($commissions as $row) {
            $id = $row['member_id'];
            $grouped[$id] = bcadd($grouped[$id] ?? '0.0000', $row['amount'], 4);
        }
        $rows = [];
        foreach ($grouped as $id => $amount) {
            $rows[] = [
                'member_id' => (string) $id,
                'member_name' => $members->get($id)->member_name ?? 'Not stored',
                'amount' => $amount,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMember(?Member $member, string $memberId, CarbonImmutable $date): array
    {
        return [
            'member_id' => $memberId,
            'member_name' => $member->member_name ?? 'Not stored',
            'sponsor_id' => $member->sponsor_id ?: 'Not stored',
            'package_name' => 'None',
            'package_code' => 'None',
            'purchase_amount' => '0.0000',
            'currency' => 'Not stored',
            'approval_at' => 'Not stored',
            'first_roi_date' => 'Not stored',
            'last_roi_date' => 'Not stored',
            'first_commission_date' => 'Not stored',
            'last_commission_date' => 'Not stored',
            'business_date' => $date->toDateString(),
            'daily_roi' => '0.0000',
            'theoretical_pool' => '0.0000',
            'payable_commission' => '0.0000',
            'credited_roi' => '0.0000',
            'credited_commission' => '0.0000',
            'pending_roi' => '0.0000',
            'pending_commission' => '0.0000',
            'labels' => ['Blocked by missing package'],
            'reasons' => ['No package purchase is stored for this member.'],
            'levels' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{roi: list<array<string, string>>, commissions: list<array<string, string>>, skipped: list<string>}  $plan
     * @return array<string, string|int>
     */
    private function totals(array $rows, array $plan): array
    {
        return [
            'rows' => count($rows),
            'planned_roi' => count($plan['roi']),
            'planned_commissions' => count($plan['commissions']),
            'pending_roi' => $this->sum(array_column($rows, 'pending_roi')),
            'pending_commission' => $this->sum(array_column($rows, 'pending_commission')),
            'payable_commission' => $this->sum(array_column($rows, 'payable_commission')),
            'theoretical_pool' => $this->sum(array_column($rows, 'theoretical_pool')),
        ];
    }

    /**
     * @param  array<string, string|int>  $empty
     * @return array<string, mixed>
     */
    private function invalid(string $businessDate, array $empty): array
    {
        return [
            'valid' => false,
            'date' => $businessDate,
            'timezone' => (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata'),
            'error' => 'Enter a business date as YYYY-MM-DD. No preview was calculated.',
            'rows' => [],
            'recipients' => [],
            'totals' => $empty,
            'stored_notice' => 'No preview was calculated.',
        ];
    }

    /**
     * @return array<string, string|int>
     */
    private function emptyTotals(): array
    {
        return [
            'rows' => 0,
            'planned_roi' => 0,
            'planned_commissions' => 0,
            'pending_roi' => '0.0000',
            'pending_commission' => '0.0000',
            'payable_commission' => '0.0000',
            'theoretical_pool' => '0.0000',
        ];
    }

    /**
     * @return array<int|string, string>
     */
    private function sums($query, string $column): array
    {
        return $query
            ->selectRaw('package_purchase_id, SUM('.$column.') as total')
            ->groupBy('package_purchase_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->package_purchase_id => (string) $row->total])
            ->all();
    }

    /**
     * @param  list<string>  $amounts
     */
    private function sum(array $amounts): string
    {
        $total = '0.0000';
        foreach ($amounts as $amount) {
            $total = bcadd($total, $this->money($amount), 4);
        }

        return $total;
    }

    private function money(mixed $amount): string
    {
        return bcadd((string) ($amount ?: '0'), '0', 4);
    }
}
