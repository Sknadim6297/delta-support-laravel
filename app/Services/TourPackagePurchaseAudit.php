<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackagePurchase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Read-only impact report for DST-TOUR-003 purchases. Never writes.
 */
class TourPackagePurchaseAudit
{
    public function __construct(
        private readonly PackageIncomePolicy $policy,
        private readonly PackageIncomeActivationGate $activation,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function report(?string $asOfDate = null): array
    {
        if (! Schema::hasTable('packages') || ! Schema::hasTable('package_purchases')) {
            throw new RuntimeException('Package tables are missing. No change was made.');
        }

        $timezone = $this->activation->timezone();
        $asOf = $asOfDate
            ? CarbonImmutable::parse($asOfDate, $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfDay();
        $waitDays = (int) config('deltasupport.package_income.wait_days', 10);
        $definition = $this->policy->definition('DST-TOUR-003');
        $package = Package::query()->where('code', 'DST-TOUR-003')->first();
        $activationDate = $this->activation->activationBusinessDate('DST-TOUR-003');

        $purchases = PackagePurchase::query()
            ->where('package_code', 'DST-TOUR-003')
            ->orderBy('id')
            ->get();

        $approved = [];
        $excludedHistoryDays = 0;
        foreach ($purchases->where('status', PackagePurchase::APPROVED) as $purchase) {
            $originalFirst = $this->activation->originalFirstAccrualDate($purchase, $this->policy);
            $allowedFirst = $this->activation->firstAllowedIncomeDate($purchase, $this->policy);
            $lastRoi = $originalFirst && $definition
                ? $this->policy->lastAccrualDate($originalFirst, (int) $definition['roi_days'])
                : null;
            $waiting = $originalFirst !== null && $asOf->lt($originalFirst);
            $beforeActivation = $activationDate !== null && $allowedFirst !== null && $asOf->lt($allowedFirst) && ! $waiting;
            $windowEndedBeforeActivation = $activationDate !== null
                && $lastRoi !== null
                && $lastRoi->lt($activationDate);

            if ($originalFirst !== null && $activationDate !== null && $originalFirst->lt($activationDate)) {
                $from = $originalFirst;
                $to = $activationDate->subDay();
                if ($to->greaterThanOrEqualTo($from)) {
                    $excludedHistoryDays += $from->diffInDays($to) + 1;
                }
            }

            $eligibleToday = $definition !== null
                && (bool) $package?->roi_eligible
                && $allowedFirst !== null
                && ! $waiting
                && $asOf->greaterThanOrEqualTo($allowedFirst)
                && $lastRoi !== null
                && $asOf->lessThanOrEqualTo($lastRoi)
                && ! $windowEndedBeforeActivation;

            $approved[] = [
                'purchase_id' => (string) $purchase->id,
                'reference' => (string) $purchase->reference,
                'member_id' => (string) $purchase->member_id,
                'reviewed_at' => $purchase->reviewed_at?->timezone($timezone)->toDateTimeString(),
                'original_first_accrual' => $originalFirst?->toDateString(),
                'activation_date' => $activationDate?->toDateString(),
                'first_allowed_income' => $allowedFirst?->toDateString(),
                'last_roi_date' => $lastRoi?->toDateString(),
                'waiting' => $waiting,
                'before_activation' => $beforeActivation,
                'window_ended_before_activation' => $windowEndedBeforeActivation,
                'eligible_today' => $eligibleToday,
            ];
        }

        $eligibleCount = count(array_filter($approved, fn (array $row): bool => $row['eligible_today']));
        $dailyRoi = $definition === null ? '0.0000' : bcmul($definition['roi_daily'], (string) $eligibleCount, 4);
        $dailyPool = $definition === null ? '0.0000' : bcmul($definition['commission_pool'], (string) $eligibleCount, 4);

        return [
            'database' => (string) DB::connection()->getDatabaseName(),
            'as_of' => $asOf->toDateString(),
            'package' => $package === null ? null : [
                'code' => 'DST-TOUR-003',
                'type' => (string) $package->package_type,
                'price' => (string) $package->price_usd,
                'roi_eligible' => (int) $package->roi_eligible,
                'level_commission_eligible' => (int) $package->level_commission_eligible,
            ],
            'definition' => $definition,
            'wait_days' => $waitDays,
            'activation_business_date' => $activationDate?->toDateString(),
            'duration_rule' => 'Original first accrual anchors the ROI/commission window. Activation is a posting floor only; no historical backfill; duration is not restarted.',
            'totals' => [
                'all' => $purchases->count(),
                'approved' => $purchases->where('status', PackagePurchase::APPROVED)->count(),
                'pending' => $purchases->where('status', PackagePurchase::PENDING)->count(),
                'rejected' => $purchases->where('status', PackagePurchase::REJECTED)->count(),
                'other' => $purchases->whereNotIn('status', [
                    PackagePurchase::APPROVED,
                    PackagePurchase::PENDING,
                    PackagePurchase::REJECTED,
                ])->count(),
                'eligible_today' => $eligibleCount,
                'waiting' => count(array_filter($approved, fn (array $row): bool => $row['waiting'])),
                'before_activation' => count(array_filter($approved, fn (array $row): bool => $row['before_activation'])),
                'window_ended_before_activation' => count(array_filter($approved, fn (array $row): bool => $row['window_ended_before_activation'])),
            ],
            'approved' => $approved,
            'historical_business_dates_excluded_estimate' => $excludedHistoryDays,
            'potential_daily_roi' => $dailyRoi,
            'potential_daily_commission_pool' => $dailyPool,
            'potential_remaining_roi_liability' => $definition === null
                ? '0.0000'
                : $this->remainingLiability($approved, $definition['roi_daily'], $asOf, 'roi'),
            'potential_remaining_commission_pool_liability' => $definition === null
                ? '0.0000'
                : $this->remainingLiability($approved, $definition['commission_pool'], $asOf, 'commission', (int) $definition['commission_days']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $approved
     */
    private function remainingLiability(array $approved, string $dailyAmount, CarbonImmutable $asOf, string $kind, ?int $commissionDays = null): string
    {
        $total = '0.0000';
        foreach ($approved as $row) {
            if ($row['window_ended_before_activation'] || $row['original_first_accrual'] === null || $row['first_allowed_income'] === null) {
                continue;
            }
            $start = CarbonImmutable::parse($row['first_allowed_income'], $this->activation->timezone())->startOfDay();
            if ($kind === 'roi') {
                if ($row['last_roi_date'] === null) {
                    continue;
                }
                $end = CarbonImmutable::parse($row['last_roi_date'], $this->activation->timezone())->startOfDay();
            } else {
                $original = CarbonImmutable::parse($row['original_first_accrual'], $this->activation->timezone())->startOfDay();
                $end = $original->addDays(($commissionDays ?? 1) - 1);
            }
            $from = $start->greaterThan($asOf) ? $start : $asOf;
            if ($from->greaterThan($end)) {
                continue;
            }
            $days = $from->diffInDays($end) + 1;
            $total = bcadd($total, bcmul($dailyAmount, (string) $days, 4), 4);
        }

        return $total;
    }
}
