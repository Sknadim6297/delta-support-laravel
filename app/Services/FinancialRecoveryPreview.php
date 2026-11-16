<?php

namespace App\Services;

use App\Models\PackageIncomeRun;
use App\Models\PackageLevelAccrual;
use App\Models\PackageRoiAccrual;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Stage A read-only recovery preview. Never writes wallets, accruals, ledgers, controls, or runs.
 */
class FinancialRecoveryPreview
{
    public function __construct(
        private readonly FinancialControls $controls,
        private readonly PackageIncomeAccrual $accrual,
        private readonly PackageIncomeActivationGate $activation,
        private readonly PostedFinancialCycleAudit $posted,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(int $lookbackDays = 14): array
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
        for ($offset = 0; $offset < $lookbackDays; $offset++) {
            $businessDate = $now->subDays($offset)->toDateString();
            $dates[] = $this->inspectBusinessDate($businessDate, $today, $yesterday, $now, $scheduleTime);
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
            'tour_activation_date' => $this->activation->activationBusinessDate(PackageIncomeActivationGate::TOUR_CODE)?->toDateString(),
            'tour_posting_authorized' => $this->activation->tourPostingAuthorized(),
            'last_succeeded_business_date' => $lastSucceeded?->business_date?->toDateString(),
            'last_succeeded_message' => $lastSucceeded?->message,
            'today' => $today,
            'yesterday' => $yesterday,
            'unique_indexes' => $unique,
            'dates' => $dates,
            'writes' => false,
        ];
    }

    /**
     * @return array<string, mixed>
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

        $plan = $this->accrual->previewReal($businessDate);
        $normalCount = 0;
        $tourCount = 0;
        $normalAmount = '0.0000';
        $tourAmount = '0.0000';
        foreach ($plan['roi'] as $row) {
            $amount = (string) $row['amount'];
            if (($row['package_code'] ?? '') === PackageIncomeActivationGate::TOUR_CODE) {
                $tourCount++;
                $tourAmount = bcadd($tourAmount, $amount, 4);
            } else {
                $normalCount++;
                $normalAmount = bcadd($normalAmount, $amount, 4);
            }
        }
        $commissionAmount = '0.0000';
        foreach ($plan['commissions'] as $row) {
            $commissionAmount = bcadd($commissionAmount, (string) $row['amount'], 4);
        }

        $existingRoi = PackageRoiAccrual::query()->whereDate('business_date', $businessDate)->get();
        $existingLevel = PackageLevelAccrual::query()->whereDate('business_date', $businessDate)->get();

        return [
            'business_date' => $businessDate,
            'latest_run_status' => $status,
            'recovery_eligibility' => $this->eligibilityLabel($businessDate, $today, $yesterday, $status, $now, $scheduleTime),
            'preview_roi_total' => (string) ($normalCount + $tourCount),
            'preview_roi_amount' => bcadd($normalAmount, $tourAmount, 4),
            'preview_normal_roi' => (string) $normalCount,
            'preview_normal_roi_amount' => $normalAmount,
            'preview_tour_roi' => (string) $tourCount,
            'preview_tour_roi_amount' => $tourAmount,
            'preview_commissions' => (string) count($plan['commissions']),
            'preview_commission_amount' => $commissionAmount,
            'existing_roi_accrued' => (string) $existingRoi->where('status', 'accrued')->count(),
            'existing_roi_calculated' => (string) $existingRoi->where('status', 'calculated')->count(),
            'existing_commission_accrued' => (string) $existingLevel->where('status', 'accrued')->count(),
            'existing_commission_calculated' => (string) $existingLevel->where('status', 'calculated')->count(),
            'skipped' => $plan['skipped'],
        ];
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
