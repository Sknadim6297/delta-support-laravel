<?php

namespace App\Services;

use App\Models\FinancialTourRecoveryRun;
use App\Models\PackageIncomeRun;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Explicit, date-selected Tour (DST-TOUR-003) recovery.
 * Separate from Standard historical recovery, which permanently refuses Tour.
 * Apply accepts exactly one business date per confirmation. Preview may list several explicit dates.
 */
class TourFinancialRecovery
{
    public const CONFIRM = 'recover-tour-package-income';

    public const BACKUP_CONFIRM = 'database-backup-confirmed';

    public const RULES_CONFIRM = 'tour-recovery-rules-confirmed';

    public const PACKAGE = PackageIncomeActivationGate::TOUR_CODE;

    public function __construct(
        private readonly PackageIncomeAccrual $accrual,
        private readonly FinancialControls $controls,
        private readonly PackageIncomeActivationGate $activation,
        private readonly PackageIncomeRunRecorder $runs,
        private readonly PackageIncomePolicy $policy,
        private readonly PostedFinancialCycleAudit $posted,
    ) {
    }

    /**
     * @param  list<string>  $dates
     * @return array<string, mixed>
     */
    public function preview(array $dates): array
    {
        return $this->execute($dates, true);
    }

    /**
     * Apply recovers exactly one explicitly approved business date.
     *
     * @return array<string, mixed>
     */
    public function apply(string $date, string $actor = 'artisan:financial:recover-tour'): array
    {
        return $this->execute([$date], false, $actor);
    }

    /**
     * @param  list<string>  $dates
     * @return array<string, mixed>
     */
    private function execute(array $dates, bool $dryRun, string $actor = 'preview'): array
    {
        $normalized = $this->normalizeDates($dates);
        if (! $dryRun && count($normalized) !== 1) {
            throw new RuntimeException('Tour recovery apply accepts exactly one --date per confirmation. No wallet was credited.');
        }

        $floor = $this->activation->activationBusinessDate(self::PACKAGE);
        $database = (string) DB::connection()->getDatabaseName();
        $days = [];
        $roiTotal = 0;
        $commissionTotal = 0;
        $roiAmount = '0.0000';
        $commissionAmount = '0.0000';
        $commissionPoolAmount = '0.0000';
        $failed = false;

        foreach ($normalized as $businessDate) {
            $day = $this->processDay($businessDate, $dryRun);
            $days[] = $day;
            $roiTotal += (int) $day['roi_records'];
            $commissionTotal += (int) $day['commission_records'];
            $roiAmount = bcadd($roiAmount, (string) $day['roi_amount'], 4);
            $commissionAmount = bcadd($commissionAmount, (string) $day['commission_amount'], 4);
            $commissionPoolAmount = bcadd($commissionPoolAmount, (string) $day['commission_pool_amount'], 4);
            if (($day['status'] ?? '') === 'failed') {
                $failed = true;
            }
        }

        $status = $dryRun
            ? FinancialTourRecoveryRun::STATUS_PREVIEWED
            : ($failed
                ? FinancialTourRecoveryRun::STATUS_FAILED
                : FinancialTourRecoveryRun::STATUS_SUCCEEDED);

        $message = $dryRun
            ? 'Tour recovery preview only. No wallets were credited. Standard packages were not touched.'
            : ($failed
                ? 'Tour recovery failed for the selected date. Idempotent retry is safe for unfinished rows.'
                : 'Tour recovery completed for the selected business date.');

        $auditId = null;
        if (Schema::hasTable('financial_tour_recovery_runs')) {
            $audit = FinancialTourRecoveryRun::query()->create([
                'database_name' => $database,
                'business_date' => $dryRun ? null : $normalized[0],
                'requested_dates' => $normalized,
                'package_code' => self::PACKAGE,
                'mode' => $dryRun ? FinancialTourRecoveryRun::MODE_PREVIEW : FinancialTourRecoveryRun::MODE_APPLY,
                'status' => $status,
                'dates_processed' => count($days),
                'roi_records' => $roiTotal,
                'commission_records' => $commissionTotal,
                'roi_amount' => $roiAmount,
                'commission_amount' => $commissionAmount,
                'commission_pool_amount' => $commissionPoolAmount,
                'day_results' => $days,
                'message' => $message,
                'actor' => $actor,
            ]);
            $auditId = $audit->id;
        }

        Log::info('Tour financial recovery '.$status, [
            'audit_id' => $auditId,
            'dates' => $normalized,
            'dry_run' => $dryRun,
            'roi' => $roiTotal,
            'commissions' => $commissionTotal,
        ]);

        $unique = [
            'roi' => false,
            'level' => false,
            'ledger_reference' => false,
            'ledger_idempotency' => false,
        ];
        try {
            $probe = $normalized[array_key_last($normalized)] ?? CarbonImmutable::now($this->activation->timezone())->toDateString();
            $unique = $this->posted->inspect([$probe])['unique_indexes'];
        } catch (Throwable) {
            // Preview/apply still returns day results.
        }

        $definition = config('deltasupport.package_income.packages.'.self::PACKAGE);

        return [
            'database' => $database,
            'audit_id' => $auditId,
            'package_code' => self::PACKAGE,
            'requested_dates' => $normalized,
            'activation_floor' => $floor?->toDateString(),
            'dry_run' => $dryRun,
            'status' => $status,
            'message' => $message,
            'automation_armed' => $this->controls->automationArmed(),
            'emergency_stop' => $this->controls->emergencyStopped(),
            'posting_allowed' => $this->controls->automaticPostingAllowed(),
            'tour_posting_authorized' => $this->activation->tourPostingAuthorized(),
            'package_terms' => is_array($definition) ? $definition : null,
            'days' => $days,
            'totals' => [
                'roi_records' => $roiTotal,
                'commission_records' => $commissionTotal,
                'roi_amount' => $roiAmount,
                'commission_amount' => $commissionAmount,
                'commission_pool_amount' => $commissionPoolAmount,
                'financial_liability' => bcadd($roiAmount, $commissionAmount, 4),
            ],
            'unique_indexes' => $unique,
            'standard_packages_touched' => false,
            'writes' => ! $dryRun && ! $failed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function processDay(string $businessDate, bool $dryRun): array
    {
        $latest = PackageIncomeRun::query()
            ->whereDate('business_date', $businessDate)
            ->latest('id')
            ->value('status');
        $latest = is_string($latest) ? $latest : 'missing';

        $existingRoi = PackageRoiAccrual::query()
            ->whereDate('business_date', $businessDate)
            ->where('package_code', self::PACKAGE)
            ->where('status', 'accrued')
            ->count();
        $existingCommission = PackageLevelAccrual::query()
            ->whereDate('business_date', $businessDate)
            ->where('package_code', self::PACKAGE)
            ->where('status', 'accrued')
            ->count();

        try {
            $result = $this->accrual->runTourRecovery($businessDate, $dryRun);
        } catch (Throwable $exception) {
            if (! $dryRun) {
                $this->runs->record(
                    $businessDate,
                    PackageIncomeRun::FAILED,
                    0,
                    0,
                    'Tour recovery failed: '.$exception->getMessage()
                );
            }

            return [
                'business_date' => $businessDate,
                'status' => 'failed',
                'previous_run_status' => $latest,
                'roi_records' => 0,
                'commission_records' => 0,
                'roi_amount' => '0.0000',
                'commission_amount' => '0.0000',
                'commission_pool_amount' => '0.0000',
                'existing_roi_accrued' => (string) $existingRoi,
                'existing_commission_accrued' => (string) $existingCommission,
                'purchases' => [],
                'roi_lines' => [],
                'commission_lines' => [],
                'error' => $exception->getMessage(),
                'skipped' => [],
            ];
        }

        $detail = $this->detailLines($result, $businessDate);

        if (! $dryRun) {
            $message = 'Tour recovery. ROI: '.$result['roi_records'].'. Commissions: '.$result['commission_records'].'.';
            $this->runs->record(
                $businessDate,
                PackageIncomeRun::SUCCEEDED,
                $result['roi_records'],
                $result['commission_records'],
                $message
            );
        }

        return [
            'business_date' => $businessDate,
            'status' => $dryRun ? 'preview' : 'succeeded',
            'previous_run_status' => $latest,
            'roi_records' => $result['roi_records'],
            'commission_records' => $result['commission_records'],
            'roi_amount' => $result['roi_amount'],
            'commission_amount' => $result['commission_amount'],
            'commission_pool_amount' => $detail['commission_pool_amount'],
            'existing_roi_accrued' => (string) $existingRoi,
            'existing_commission_accrued' => (string) $existingCommission,
            'purchases' => $detail['purchases'],
            'roi_lines' => $detail['roi_lines'],
            'commission_lines' => $detail['commission_lines'],
            'error' => null,
            'skipped' => $result['skipped'],
        ];
    }

    /**
     * @param  array{roi: list<array<string,string>>, commissions: list<array<string,string>>, roi_amount: string, commission_amount: string}  $result
     * @return array{purchases: list<array<string,string>>, roi_lines: list<array<string,string>>, commission_lines: list<array<string,string>>, commission_pool_amount: string}
     */
    private function detailLines(array $result, string $businessDate): array
    {
        $purchaseIds = collect($result['roi'])
            ->merge($result['commissions'])
            ->pluck('purchase_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $purchasesById = PackagePurchase::query()
            ->whereIn('id', $purchaseIds)
            ->get()
            ->keyBy(fn (PackagePurchase $row): string => (string) $row->id);

        $definition = $this->policy->definition(self::PACKAGE);
        $poolPerPurchase = is_array($definition) ? (string) ($definition['commission_pool'] ?? '0.0000') : '0.0000';
        $roiDaily = is_array($definition) ? (string) ($definition['roi_daily'] ?? '0.0000') : '0.0000';

        $purchases = [];
        $seen = [];
        foreach ($result['roi'] as $row) {
            $id = (string) ($row['purchase_id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $purchase = $purchasesById->get($id);
            $purchases[] = [
                'purchase_id' => $id,
                'reference' => (string) ($purchase?->reference ?? ''),
                'member_id' => (string) ($row['member_id'] ?? ''),
                'package_code' => self::PACKAGE,
                'business_date' => $businessDate,
                'roi_daily' => (string) ($row['amount'] ?? $roiDaily),
                'commission_pool' => $poolPerPurchase,
                'action' => 'pending_or_recoverable',
            ];
        }

        $poolTotal = '0.0000';
        foreach ($purchases as $purchase) {
            $poolTotal = bcadd($poolTotal, (string) $purchase['commission_pool'], 4);
        }

        $roiLines = array_map(function (array $row) use ($purchasesById): array {
            $purchase = $purchasesById->get((string) ($row['purchase_id'] ?? ''));

            return [
                'purchase_reference' => (string) ($purchase?->reference ?? ''),
                'member_id' => (string) ($row['member_id'] ?? ''),
                'package_code' => (string) ($row['package_code'] ?? self::PACKAGE),
                'business_date' => (string) ($row['business_date'] ?? ''),
                'amount' => (string) ($row['amount'] ?? '0.0000'),
                'ledger_reference' => (string) ($row['reference'] ?? ''),
            ];
        }, $result['roi']);

        $commissionLines = array_map(function (array $row) use ($purchasesById): array {
            $purchase = $purchasesById->get((string) ($row['purchase_id'] ?? ''));

            return [
                'purchase_reference' => (string) ($purchase?->reference ?? ''),
                'from_member_id' => (string) ($row['from_member_id'] ?? ''),
                'recipient_member_id' => (string) ($row['member_id'] ?? ''),
                'level' => (string) ($row['level'] ?? ''),
                'rate' => (string) ($row['rate'] ?? ''),
                'pool' => (string) ($row['pool'] ?? ''),
                'amount' => (string) ($row['amount'] ?? '0.0000'),
                'business_date' => (string) ($row['business_date'] ?? ''),
                'ledger_reference' => (string) ($row['reference'] ?? ''),
            ];
        }, $result['commissions']);

        return [
            'purchases' => $purchases,
            'roi_lines' => $roiLines,
            'commission_lines' => $commissionLines,
            'commission_pool_amount' => $poolTotal,
        ];
    }

    /**
     * @param  list<string>  $dates
     * @return list<string>
     */
    private function normalizeDates(array $dates): array
    {
        $timezone = $this->activation->timezone();
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $floor = $this->activation->activationBusinessDate(self::PACKAGE);
        $maxDates = max(1, (int) config('deltasupport.package_income.tour_recovery.max_dates', 7));

        $normalized = [];
        foreach ($dates as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                throw new RuntimeException('Tour recovery dates must be YYYY-MM-DD. No wallet was credited.');
            }
            $date = CarbonImmutable::parse($value, $timezone)->startOfDay();
            if ($date->greaterThan($today)) {
                throw new RuntimeException('Tour recovery cannot include future date '.$value.'. No wallet was credited.');
            }
            if ($floor === null) {
                throw new RuntimeException(self::PACKAGE.' activation floor is missing. No wallet was credited.');
            }
            if ($date->lessThan($floor)) {
                throw new RuntimeException(
                    'Tour recovery refuses '.$value.' before activation floor '.$floor->toDateString().'. No wallet was credited.'
                );
            }
            $normalized[$date->toDateString()] = $date->toDateString();
        }

        $normalized = array_values($normalized);
        if ($normalized === []) {
            throw new RuntimeException('Tour recovery requires at least one explicit business date. No wallet was credited.');
        }
        if (count($normalized) > $maxDates) {
            throw new RuntimeException('Tour recovery allows at most '.$maxDates.' explicit dates per preview. No wallet was credited.');
        }

        sort($normalized);

        return $normalized;
    }
}
