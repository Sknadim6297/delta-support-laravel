<?php

namespace App\Console\Commands;

use App\Models\PackageIncomeRun;
use App\Models\PackageLevelAccrual;
use App\Models\PackageRoiAccrual;
use App\Services\PackageIncomeAccrual;
use App\Services\PackageIncomeRunRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class AccruePackageIncome extends Command
{
    protected $signature = 'package-income:accrue {--date= : One Asia/Kolkata business date (YYYY-MM-DD)}';

    protected $description = 'Accrue package ROI and level commission for one business date when generation is enabled';

    public function handle(PackageIncomeAccrual $accrual, PackageIncomeRunRecorder $runs): int
    {
        $date = $this->option('date')
            ?: CarbonImmutable::now(config('deltasupport.package_income.timezone'))->toDateString();

        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $runs->record(null, PackageIncomeRun::FAILED, 0, 0, 'The business date is not a YYYY-MM-DD value. No wallet was credited.');
            $this->error('The business date is not a YYYY-MM-DD value. No wallet was credited.');

            return 1;
        }

        $roiBefore = PackageRoiAccrual::query()->whereDate('business_date', $date)->count();
        $levelBefore = PackageLevelAccrual::query()->whereDate('business_date', $date)->count();

        try {
            $result = $accrual->accrue($date);
        } catch (Throwable $exception) {
            $status = $this->status($exception);
            $runs->record(
                $date,
                $status,
                PackageRoiAccrual::query()->whereDate('business_date', $date)->count() - $roiBefore,
                PackageLevelAccrual::query()->whereDate('business_date', $date)->count() - $levelBefore,
                $exception->getMessage()
            );
            $this->error($exception->getMessage());

            return 1;
        }

        $message = 'ROI credits: '.$result['credited_roi'].'. Commission credits: '.$result['credited_commissions'].'. No earlier date was processed.';
        $runs->record($date, PackageIncomeRun::SUCCEEDED, $result['credited_roi'], $result['credited_commissions'], $message);
        $this->info($message);

        return 0;
    }

    private function status(Throwable $exception): string
    {
        if (! $exception instanceof RuntimeException) {
            return PackageIncomeRun::FAILED;
        }
        if (str_contains($exception->getMessage(), 'disabled')) {
            return PackageIncomeRun::REFUSED;
        }
        if (str_contains($exception->getMessage(), 'already running')) {
            return PackageIncomeRun::BLOCKED;
        }

        return PackageIncomeRun::FAILED;
    }
}
