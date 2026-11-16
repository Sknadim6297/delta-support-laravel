<?php

namespace App\Services;

use App\Models\PackageIncomeRun;
use App\Models\PackageLevelAccrual;
use App\Models\PackageRoiAccrual;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Stage A read-only recovery preview. Never writes wallets, accruals, ledgers, controls, or runs.
 */
class FinancialRecoveryPreview
{
    public const ROI_100 = 'DST-ROI-002';

    public const ROI_1000 = 'DST-TOUR-003';

    public function __construct(
        private readonly FinancialControls $controls,
        private readonly PackageIncomeAccrual $accrual,
        private readonly PackageIncomeActivationGate $activation,
        private readonly PostedFinancialCycleAudit $posted,
        private readonly InProcessScheduleTick $tick,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(int $lookbackDays = 14, bool $detailed = false): array
    {
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $scheduleTime = (string) config('deltasupport.package_income.schedule_time', '00:10');
        $now = CarbonImmutable::now($timezone);
        $today = $now->toDateString();
        $yesterday = $now->subDay()->toDateString();
        $lookbackDays = max(1, min(90, $lookbackDays));

        $lastSucceeded = PackageIncomeRun::query()
            ->where('status', PackageIncomeRun::SUCCEEDED)
            ->latest('id')
            ->first();

        $dates = [];
        $roiLines = [];
        $commissionLines = [];
        $totals = [
            'expected_roi_100_amount' => '0.0000',
            'expected_roi_1000_amount' => '0.0000',
            'expected_roi_amount' => '0.0000',
            'expected_commission_amount' => '0.0000',
            'missing_roi' => 0,
            'calculated_roi' => 0,
            'posted_roi' => 0,
            'missing_commission' => 0,
            'calculated_commission' => 0,
            'posted_commission' => 0,
            'duplicate_roi_keys' => 0,
            'duplicate_commission_keys' => 0,
        ];

        for ($offset = 0; $offset < $lookbackDays; $offset++) {
            $businessDate = $now->subDays($offset)->toDateString();
            $day = $this->inspectBusinessDate($businessDate, $today, $yesterday, $now, $scheduleTime);
            $dates[] = $day['summary'];
            foreach ($day['roi_lines'] as $line) {
                $this->accumulateRoiTotals($totals, $line);
                if ($detailed) {
                    $roiLines[] = $line;
                }
            }
            foreach ($day['commission_lines'] as $line) {
                $this->accumulateCommissionTotals($totals, $line);
                if ($detailed) {
                    $commissionLines[] = $line;
                }
            }
            $totals['duplicate_roi_keys'] += $day['duplicate_roi_keys'];
            $totals['duplicate_commission_keys'] += $day['duplicate_commission_keys'];
        }

        $unique = [
            'roi' => false,
            'level' => false,
            'ledger_reference' => false,
            'ledger_idempotency' => false,
        ];
        try {
            $unique = $this->posted->inspect([$yesterday])['unique_indexes'];
        } catch (Throwable) {
            // Preview still returns plan data when index inspection is unavailable.
        }

        return [
            'database' => (string) DB::connection()->getDatabaseName(),
            'timezone' => $timezone,
            'schedule_time' => $scheduleTime,
            'now' => $now->toDateTimeString(),
            'scheduler_mode' => (string) (
                env('DELTASUPPORT_SCHEDULER_MODE')
                ?: config('deltasupport.scheduler.mode', 'dry-run')
            ),
            'scheduler_allow_financial' => filter_var(
                env('DELTASUPPORT_SCHEDULER_ALLOW_FINANCIAL', config('deltasupport.scheduler.allow_financial', false)),
                FILTER_VALIDATE_BOOLEAN
            ),
            'automation_armed' => $this->controls->automationArmed(),
            'emergency_stop' => $this->controls->emergencyStopped(),
            'posting_allowed' => $this->controls->automaticPostingAllowed(),
            'tour_activation_date' => $this->activation->activationBusinessDate(self::ROI_1000)?->toDateString(),
            'tour_posting_authorized' => $this->activation->tourPostingAuthorized(),
            'package_terms' => [
                self::ROI_100 => config('deltasupport.package_income.packages.'.self::ROI_100),
                self::ROI_1000 => config('deltasupport.package_income.packages.'.self::ROI_1000),
            ],
            'generation_blockers' => $this->generationBlockers(),
            'last_succeeded_business_date' => $lastSucceeded?->business_date?->toDateString(),
            'last_succeeded_message' => $lastSucceeded?->message,
            'today' => $today,
            'yesterday' => $yesterday,
            'unique_indexes' => $unique,
            'dates' => $dates,
            'roi_lines' => $detailed ? $roiLines : [],
            'commission_lines' => $detailed ? $commissionLines : [],
            'totals' => $totals,
            'future_schedule_simulation' => $this->simulateFutureSchedule($now, $timezone, $scheduleTime),
            'writes' => false,
        ];
    }

    /**
     * @return list<string>
     */
    public function generationBlockers(): array
    {
        $blockers = [];
        $mode = strtolower((string) (
            env('DELTASUPPORT_SCHEDULER_MODE')
            ?: config('deltasupport.scheduler.mode', 'dry-run')
        ));
        $allow = filter_var(
            env('DELTASUPPORT_SCHEDULER_ALLOW_FINANCIAL', config('deltasupport.scheduler.allow_financial', false)),
            FILTER_VALIDATE_BOOLEAN
        );

        if (in_array($mode, ['verify', 'verify-only', 'dry-run', 'dryrun'], true)) {
            $blockers[] = 'Scheduler mode='.$mode.' disables all Artisan job execution (including financial:daily).';
        }
        if (! $allow) {
            $blockers[] = 'DELTASUPPORT_SCHEDULER_ALLOW_FINANCIAL=false blocks financial jobs even in live mode.';
        }
        if (! $this->controls->automationArmed()) {
            $blockers[] = 'Financial automation is not armed; financial:daily refuses wallet credits.';
        }
        if ($this->controls->emergencyStopped()) {
            $blockers[] = 'Emergency stop is on; automatic posting flags are forced off.';
        }
        if ($this->activation->activationBusinessDate(self::ROI_1000) === null) {
            $blockers[] = self::ROI_1000.' activation floor is missing; Tour income is fail-closed.';
        } elseif (! $this->activation->tourPostingAuthorized()) {
            $blockers[] = self::ROI_1000.' activation floor exists but Tour posting is not authorized (DELTASUPPORT_TOUR_INCOME_POSTING_AUTHORIZED=false).';
        }
        $terms100 = config('deltasupport.package_income.packages.'.self::ROI_100);
        $terms1000 = config('deltasupport.package_income.packages.'.self::ROI_1000);
        if (! is_array($terms100) || ($terms100['roi_daily'] ?? null) !== '0.2500') {
            $blockers[] = self::ROI_100.' terms are missing or do not match approved 0.2500 daily.';
        }
        if (! is_array($terms1000) || ($terms1000['roi_daily'] ?? null) !== '2.5000') {
            $blockers[] = self::ROI_1000.' terms are missing or do not match approved 2.5000 daily.';
        }

        return $blockers;
    }

    /**
     * @return list<array{at: string, command: string, due: bool, would_execute: bool, skip: string}>
     */
    public function simulateFutureSchedule(CarbonImmutable $from, string $timezone, string $scheduleTime): array
    {
        $slots = [
            $from->setTimeFromTimeString($scheduleTime),
            $from->setTime(0, 20, 0),
            $from->setTime(0, 25, 0),
            $from->addDay()->setTimeFromTimeString($scheduleTime),
        ];
        $rows = [];
        foreach ($slots as $slot) {
            if ($slot->lessThan($from->startOfMinute())) {
                $slot = $slot->addDay();
            }
            // Use jobs() only — never call tick->run() here (that appends verify logs).
            foreach ($this->tick->jobs($slot, $scheduleTime) as $job) {
                if (! $job['due']) {
                    continue;
                }
                $rows[] = [
                    'at' => $slot->toDateTimeString().' '.$timezone,
                    'command' => $job['command'],
                    'due' => true,
                    'would_execute' => false,
                    'skip' => 'verify-only simulation: execution disabled',
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array{summary: array<string, mixed>, roi_lines: list<array<string, mixed>>, commission_lines: list<array<string, mixed>>, duplicate_roi_keys: int, duplicate_commission_keys: int}
     */
    private function inspectBusinessDate(
        string $businessDate,
        string $today,
        string $yesterday,
        CarbonImmutable $now,
        string $scheduleTime,
    ): array {
        $status = PackageIncomeRun::query()
            ->whereDate('business_date', $businessDate)
            ->latest('id')
            ->value('status');
        $status = is_string($status) ? $status : 'missing';

        $actualPlan = $this->accrual->previewReal($businessDate);
        $expectedPlan = $this->expectedPlanIncludingAuthorizedTour($businessDate);

        $existingRoi = PackageRoiAccrual::query()->whereDate('business_date', $businessDate)->get();
        $existingLevel = PackageLevelAccrual::query()->whereDate('business_date', $businessDate)->get();

        $roiByPurchase = $existingRoi->keyBy(fn ($row) => (string) $row->package_purchase_id);
        $levelByKey = $existingLevel->keyBy(fn ($row) => $row->package_purchase_id.'|'.$row->member_id);

        $roiLines = [];
        $normalExpected = 0;
        $tourExpected = 0;
        $normalExpectedAmount = '0.0000';
        $tourExpectedAmount = '0.0000';
        $actualRoiAmount = '0.0000';

        foreach ($expectedPlan['roi'] as $row) {
            $purchaseId = (string) $row['purchase_id'];
            $code = (string) ($row['package_code'] ?? '');
            $amount = (string) $row['amount'];
            $existing = $roiByPurchase->get($purchaseId);
            $ledger = $this->ledgerExists((string) $row['reference']);
            $lineStatus = $this->roiStatus($existing, $ledger);
            $blocked = $this->roiBlockedReason($code, $actualPlan);

            if ($code === self::ROI_1000) {
                $tourExpected++;
                $tourExpectedAmount = bcadd($tourExpectedAmount, $amount, 4);
            } else {
                $normalExpected++;
                $normalExpectedAmount = bcadd($normalExpectedAmount, $amount, 4);
            }

            $inActual = collect($actualPlan['roi'])->contains(fn (array $a): bool => (string) $a['purchase_id'] === $purchaseId);
            if ($inActual) {
                $actualRoiAmount = bcadd($actualRoiAmount, $amount, 4);
            }

            $roiLines[] = [
                'business_date' => $businessDate,
                'member_id' => $row['member_id'],
                'purchase_id' => $purchaseId,
                'package_code' => $code,
                'amount' => $amount,
                'reference' => $row['reference'],
                'expected' => 'yes',
                'allowed_under_current_gates' => $inActual ? 'yes' : 'no',
                'record_status' => $lineStatus,
                'ledger' => $ledger ? 'yes' : 'no',
                'block_reason' => $blocked,
            ];
        }

        $commissionLines = [];
        $commissionExpectedAmount = '0.0000';
        $actualCommissionAmount = '0.0000';
        foreach ($expectedPlan['commissions'] as $row) {
            $key = $row['purchase_id'].'|'.$row['member_id'];
            $amount = (string) $row['amount'];
            $existing = $levelByKey->get($key);
            $ledger = $this->ledgerExists((string) $row['reference']);
            $lineStatus = $this->commissionStatus($existing, $ledger);
            $inActual = collect($actualPlan['commissions'])->contains(
                fn (array $a): bool => (string) $a['purchase_id'] === (string) $row['purchase_id']
                    && (string) $a['member_id'] === (string) $row['member_id']
            );
            $commissionExpectedAmount = bcadd($commissionExpectedAmount, $amount, 4);
            if ($inActual) {
                $actualCommissionAmount = bcadd($actualCommissionAmount, $amount, 4);
            }
            $commissionLines[] = [
                'business_date' => $businessDate,
                'member_id' => $row['member_id'],
                'from_member_id' => $row['from_member_id'],
                'purchase_id' => (string) $row['purchase_id'],
                'package_code' => (string) ($row['package_code'] ?? ''),
                'level' => (string) ($row['level'] ?? ''),
                'amount' => $amount,
                'reference' => $row['reference'],
                'expected' => 'yes',
                'allowed_under_current_gates' => $inActual ? 'yes' : 'no',
                'record_status' => $lineStatus,
                'ledger' => $ledger ? 'yes' : 'no',
            ];
        }

        $duplicateRoi = $this->duplicateCount('package_roi_accruals', ['package_purchase_id', 'business_date'], $businessDate);
        $duplicateLevel = $this->duplicateCount('package_level_accruals', ['package_purchase_id', 'member_id', 'business_date'], $businessDate);

        $summary = [
            'business_date' => $businessDate,
            'latest_run_status' => $status,
            'recovery_eligibility' => $this->eligibilityLabel($businessDate, $today, $yesterday, $status, $now, $scheduleTime),
            'expected_normal_roi' => (string) $normalExpected,
            'expected_normal_roi_amount' => $normalExpectedAmount,
            'expected_tour_roi' => (string) $tourExpected,
            'expected_tour_roi_amount' => $tourExpectedAmount,
            'expected_commissions' => (string) count($expectedPlan['commissions']),
            'expected_commission_amount' => $commissionExpectedAmount,
            'actual_gate_roi' => (string) count($actualPlan['roi']),
            'actual_gate_roi_amount' => $actualRoiAmount,
            'actual_gate_commissions' => (string) count($actualPlan['commissions']),
            'actual_gate_commission_amount' => $actualCommissionAmount,
            'existing_roi_accrued' => (string) $existingRoi->where('status', 'accrued')->count(),
            'existing_roi_calculated' => (string) $existingRoi->where('status', 'calculated')->count(),
            'existing_commission_accrued' => (string) $existingLevel->where('status', 'accrued')->count(),
            'existing_commission_calculated' => (string) $existingLevel->where('status', 'calculated')->count(),
            'skipped' => array_values(array_unique([...$expectedPlan['skipped'], ...$actualPlan['skipped']])),
        ];

        return [
            'summary' => $summary,
            'roi_lines' => $roiLines,
            'commission_lines' => $commissionLines,
            'duplicate_roi_keys' => $duplicateRoi,
            'duplicate_commission_keys' => $duplicateLevel,
        ];
    }

    /**
     * Expected plan treats Tour as authorized when an activation floor exists, so Stage A can show
     * $1000 projections without enabling production Tour posting.
     *
     * @return array{date: string, enabled: bool, roi: list<array<string, string>>, commissions: list<array<string, string>>, skipped: list<string>}
     */
    private function expectedPlanIncludingAuthorizedTour(string $businessDate): array
    {
        $previous = config('deltasupport.package_income.tour_posting_authorized');
        $hasFloor = $this->activation->activationBusinessDate(self::ROI_1000) !== null;
        try {
            if ($hasFloor) {
                config(['deltasupport.package_income.tour_posting_authorized' => true]);
            }

            return $this->accrual->previewReal($businessDate);
        } finally {
            config(['deltasupport.package_income.tour_posting_authorized' => $previous]);
        }
    }

    private function roiBlockedReason(string $packageCode, array $actualPlan): string
    {
        if ($packageCode !== self::ROI_1000) {
            return '-';
        }
        if ($this->activation->activationBusinessDate(self::ROI_1000) === null) {
            return 'tour activation floor missing';
        }
        if (! $this->activation->tourPostingAuthorized()) {
            return 'tour posting not authorized';
        }
        foreach ($actualPlan['skipped'] as $reason) {
            if (str_contains($reason, self::ROI_1000) || str_contains($reason, 'Tour') || str_contains($reason, 'posting is not authorized')) {
                return $reason;
            }
        }

        return '-';
    }

    private function roiStatus(?object $existing, bool $ledger): string
    {
        if ($existing === null) {
            return 'missing';
        }
        if ($existing->status === 'accrued') {
            return $ledger ? 'posted' : 'posted-without-ledger';
        }
        if ($existing->status === 'calculated') {
            return 'calculated-only';
        }

        return (string) $existing->status;
    }

    private function commissionStatus(?object $existing, bool $ledger): string
    {
        return $this->roiStatus($existing, $ledger);
    }

    private function ledgerExists(string $reference): bool
    {
        if ($reference === '' || ! Schema::hasTable('wallet_ledger_entries')) {
            return false;
        }

        return DB::table('wallet_ledger_entries')->where('reference', $reference)->exists();
    }

    /**
     * @param  list<string>  $columns
     */
    private function duplicateCount(string $table, array $columns, string $businessDate): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }
        $select = implode(', ', $columns);
        $rows = DB::table($table)
            ->selectRaw($select.', COUNT(*) as aggregate')
            ->whereDate('business_date', $businessDate)
            ->groupBy(...$columns)
            ->having('aggregate', '>', 1)
            ->get();

        return $rows->count();
    }

    /**
     * @param  array<string, int|string>  $totals
     * @param  array<string, mixed>  $line
     */
    private function accumulateRoiTotals(array &$totals, array $line): void
    {
        $amount = (string) $line['amount'];
        if ($line['package_code'] === self::ROI_100) {
            $totals['expected_roi_100_amount'] = bcadd((string) $totals['expected_roi_100_amount'], $amount, 4);
        }
        if ($line['package_code'] === self::ROI_1000) {
            $totals['expected_roi_1000_amount'] = bcadd((string) $totals['expected_roi_1000_amount'], $amount, 4);
        }
        $totals['expected_roi_amount'] = bcadd((string) $totals['expected_roi_amount'], $amount, 4);
        match ($line['record_status']) {
            'missing' => $totals['missing_roi']++,
            'calculated-only' => $totals['calculated_roi']++,
            'posted', 'posted-without-ledger' => $totals['posted_roi']++,
            default => null,
        };
    }

    /**
     * @param  array<string, int|string>  $totals
     * @param  array<string, mixed>  $line
     */
    private function accumulateCommissionTotals(array &$totals, array $line): void
    {
        $totals['expected_commission_amount'] = bcadd((string) $totals['expected_commission_amount'], (string) $line['amount'], 4);
        match ($line['record_status']) {
            'missing' => $totals['missing_commission']++,
            'calculated-only' => $totals['calculated_commission']++,
            'posted', 'posted-without-ledger' => $totals['posted_commission']++,
            default => null,
        };
    }

    private function eligibilityLabel(
        string $businessDate,
        string $today,
        string $yesterday,
        string $status,
        CarbonImmutable $now,
        string $scheduleTime,
    ): string {
        if ($status === PackageIncomeRun::SUCCEEDED) {
            return 'already succeeded — no credit again';
        }
        if ($businessDate === $yesterday) {
            return 'eligible via financial:recover (yesterday) when armed and approved';
        }
        if ($businessDate === $today) {
            if ($now->format('H:i') < $scheduleTime) {
                return 'today — waiting for schedule_time '.$scheduleTime;
            }

            return 'eligible via financial:daily / financial:recover (today) when armed and approved';
        }

        return 'NOT eligible for automatic recovery — requires separate written approval; no silent backfill';
    }
}
