<?php

namespace App\Services;

use App\Models\FinancialHistoricalRecoveryRun;
use App\Models\PackageIncomeRun;
use App\Models\PackageLevelAccrual;
use App\Models\PackageRoiAccrual;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Controlled Standard-package historical recovery.
 * Tour / activation-gated packages are refused. Preview writes audit rows only when apply is requested;
 * preview mode records an audit row marked previewed without wallet changes.
 */
class HistoricalFinancialRecovery
{
    public const CONFIRM = 'recover-historical-standard-income';

    public const BACKUP_CONFIRM = 'database-backup-confirmed';

    public const RULES_CONFIRM = 'business-rules-confirmed';

    public function __construct(
        private readonly PackageIncomeAccrual $accrual,
        private readonly FinancialControls $controls,
        private readonly PackageIncomeActivationGate $activation,
        private readonly PackageIncomeRunRecorder $runs,
        private readonly PostedFinancialCycleAudit $posted,
    ) {
    }

    /**
     * @param  list<string>|null  $packages
     * @return array<string, mixed>
     */
    public function preview(string $from, string $to, ?array $packages = null): array
    {
        return $this->execute($from, $to, $packages, true);
    }

    /**
     * @param  list<string>|null  $packages
     * @return array<string, mixed>
     */
    public function apply(string $from, string $to, ?array $packages = null, string $actor = 'artisan:financial:recover-historical'): array
    {
        return $this->execute($from, $to, $packages, false, $actor);
    }

    /**
     * @return list<string>
     */
    public function defaultAllowedPackages(): array
    {
        $configured = config('deltasupport.package_income.historical_recovery.allowed_packages', ['DST-ROI-002']);

        return is_array($configured) ? array_values(array_map('strval', $configured)) : ['DST-ROI-002'];
    }

    /**
     * @param  list<string>|null  $packages
     * @return array<string, mixed>
     */
    private function execute(string $from, string $to, ?array $packages, bool $dryRun, string $actor = 'preview'): array
    {
        [$fromDate, $toDate, $packageCodes] = $this->validateRangeAndPackages($from, $to, $packages);
        $database = (string) DB::connection()->getDatabaseName();
        $days = [];
        $cursor = $fromDate;
        $roiTotal = 0;
        $commissionTotal = 0;
        $roiAmount = '0.0000';
        $commissionAmount = '0.0000';
        $failed = false;
        $processed = 0;

        while ($cursor->lessThanOrEqualTo($toDate)) {
            $businessDate = $cursor->toDateString();
            $day = $this->processDay($businessDate, $packageCodes, $dryRun);
            $days[] = $day;
            $processed++;
            $roiTotal += (int) $day['roi_records'];
            $commissionTotal += (int) $day['commission_records'];
            $roiAmount = bcadd($roiAmount, (string) $day['roi_amount'], 4);
            $commissionAmount = bcadd($commissionAmount, (string) $day['commission_amount'], 4);
            if (($day['status'] ?? '') === 'failed') {
                $failed = true;
            }
            $cursor = $cursor->addDay();
        }

        $status = $dryRun
            ? FinancialHistoricalRecoveryRun::STATUS_PREVIEWED
            : ($failed
                ? (collect($days)->contains(fn (array $d): bool => ($d['status'] ?? '') === 'succeeded')
                    ? FinancialHistoricalRecoveryRun::STATUS_PARTIAL
                    : FinancialHistoricalRecoveryRun::STATUS_FAILED)
                : FinancialHistoricalRecoveryRun::STATUS_SUCCEEDED);

        $message = $dryRun
            ? 'Preview only. No wallets were credited.'
            : ($failed
                ? 'Historical recovery finished with one or more failed dates. Idempotent retry is safe for unfinished rows.'
                : 'Historical Standard recovery completed.');

        $auditId = null;
        if (Schema::hasTable('financial_historical_recovery_runs')) {
            $audit = FinancialHistoricalRecoveryRun::query()->create([
                'database_name' => $database,
                'from_date' => $fromDate->toDateString(),
                'to_date' => $toDate->toDateString(),
                'package_codes' => $packageCodes,
                'mode' => $dryRun ? FinancialHistoricalRecoveryRun::MODE_PREVIEW : FinancialHistoricalRecoveryRun::MODE_APPLY,
                'status' => $status,
                'days_processed' => $processed,
                'roi_records' => $roiTotal,
                'commission_records' => $commissionTotal,
                'roi_amount' => $roiAmount,
                'commission_amount' => $commissionAmount,
                'day_results' => $days,
                'message' => $message,
                'actor' => $actor,
            ]);
            $auditId = $audit->id;
        }

        Log::info('Historical financial recovery '.$status, [
            'audit_id' => $auditId,
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'packages' => $packageCodes,
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
            $unique = $this->posted->inspect([$toDate->toDateString()])['unique_indexes'];
        } catch (Throwable) {
            // Preview/apply still returns day results.
        }

        return [
            'database' => $database,
            'audit_id' => $auditId,
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'packages' => $packageCodes,
            'dry_run' => $dryRun,
            'status' => $status,
            'message' => $message,
            'automation_armed' => $this->controls->automationArmed(),
            'emergency_stop' => $this->controls->emergencyStopped(),
            'posting_allowed' => $this->controls->automaticPostingAllowed(),
            'tour_posting_authorized' => $this->activation->tourPostingAuthorized(),
            'tour_excluded' => true,
            'days' => $days,
            'totals' => [
                'roi_records' => $roiTotal,
                'commission_records' => $commissionTotal,
                'roi_amount' => $roiAmount,
                'commission_amount' => $commissionAmount,
            ],
            'unique_indexes' => $unique,
            'writes' => ! $dryRun,
        ];
    }

    /**
     * @param  list<string>  $packageCodes
     * @return array<string, mixed>
     */
    private function processDay(string $businessDate, array $packageCodes, bool $dryRun): array
    {
        $latest = PackageIncomeRun::query()
            ->whereDate('business_date', $businessDate)
            ->latest('id')
            ->value('status');
        $latest = is_string($latest) ? $latest : 'missing';

        try {
            $result = $this->accrual->runStandardHistorical($businessDate, $dryRun, $packageCodes);
        } catch (Throwable $exception) {
            if (! $dryRun) {
                $this->runs->record(
                    $businessDate,
                    PackageIncomeRun::FAILED,
                    0,
                    0,
                    'Historical recovery failed: '.$exception->getMessage()
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
                'existing_roi_accrued' => (string) PackageRoiAccrual::query()->whereDate('business_date', $businessDate)->where('status', 'accrued')->whereIn('package_code', $packageCodes)->count(),
                'existing_roi_calculated' => (string) PackageRoiAccrual::query()->whereDate('business_date', $businessDate)->where('status', 'calculated')->whereIn('package_code', $packageCodes)->count(),
                'error' => $exception->getMessage(),
                'skipped' => [],
            ];
        }

        if (! $dryRun) {
            $message = 'Historical Standard recovery. ROI: '.$result['roi_records'].'. Commissions: '.$result['commission_records'].'.';
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
            'existing_roi_accrued' => (string) PackageRoiAccrual::query()->whereDate('business_date', $businessDate)->where('status', 'accrued')->whereIn('package_code', $packageCodes)->count(),
            'existing_roi_calculated' => (string) PackageRoiAccrual::query()->whereDate('business_date', $businessDate)->where('status', 'calculated')->whereIn('package_code', $packageCodes)->count(),
            'existing_commission_accrued' => (string) PackageLevelAccrual::query()->whereDate('business_date', $businessDate)->where('status', 'accrued')->whereIn('package_code', $packageCodes)->count(),
            'existing_commission_calculated' => (string) PackageLevelAccrual::query()->whereDate('business_date', $businessDate)->where('status', 'calculated')->whereIn('package_code', $packageCodes)->count(),
            'error' => null,
            'skipped' => $result['skipped'],
        ];
    }

    /**
     * @param  list<string>|null  $packages
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: list<string>}
     */
    private function validateRangeAndPackages(string $from, string $to, ?array $packages): array
    {
        foreach ([$from, $to] as $value) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                throw new RuntimeException('Dates must be YYYY-MM-DD. No wallet was credited.');
            }
        }

        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $fromDate = CarbonImmutable::parse($from, $timezone)->startOfDay();
        $toDate = CarbonImmutable::parse($to, $timezone)->startOfDay();
        $today = CarbonImmutable::now($timezone)->startOfDay();

        if ($fromDate->greaterThan($toDate)) {
            throw new RuntimeException('--from must be on or before --to. No wallet was credited.');
        }
        if ($toDate->greaterThan($today)) {
            throw new RuntimeException('Historical recovery cannot include future dates. No wallet was credited.');
        }

        $maxDays = max(1, (int) config('deltasupport.package_income.historical_recovery.max_days', 90));
        if ($fromDate->diffInDays($toDate) + 1 > $maxDays) {
            throw new RuntimeException('Historical recovery range exceeds '.$maxDays.' days. No wallet was credited.');
        }

        $packageCodes = $packages === null || $packages === []
            ? $this->defaultAllowedPackages()
            : array_values(array_unique(array_filter(array_map('strval', $packages))));

        $allowed = $this->defaultAllowedPackages();
        if (app()->environment('testing')) {
            $allowed[] = 'DEMO-COAST';
        }
        $allowed = array_values(array_unique($allowed));

        $gated = $this->activation->gatedPackageCodes();
        foreach ($packageCodes as $code) {
            if ($code === PackageIncomeActivationGate::TOUR_CODE || in_array($code, $gated, true)) {
                throw new RuntimeException('Tour/activation-gated package '.$code.' cannot be historically recovered. No wallet was credited.');
            }
            if (! in_array($code, $allowed, true)) {
                throw new RuntimeException('Package '.$code.' is not on the Standard historical allowlist. No wallet was credited.');
            }
        }

        return [$fromDate, $toDate, $packageCodes];
    }
}
