<?php

namespace App\Console\Commands;

use App\Models\PackageIncomeRun;
use App\Services\FinancialControls;
use App\Services\PackageIncomeAccrual;
use App\Services\PackageIncomeRunRecorder;
use App\Services\WalletBalanceReconciliation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class RunDailyFinancialCycle extends Command
{
    protected $signature = 'financial:daily {--dry-run : Preview one business date without writing} {--date= : Asia/Kolkata date. An actual run accepts only today.} {--recover-yesterday : Retry yesterday only after a failed run}';

    protected $description = 'Process one Asia/Kolkata business date when financial automation is armed. An emergency stop posts nothing.';

    public function handle(
        PackageIncomeAccrual $accrual,
        WalletBalanceReconciliation $reconciliation,
        FinancialControls $controls,
        PackageIncomeRunRecorder $runs,
    ): int {
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $date = $this->option('date') ?: CarbonImmutable::now($timezone)->toDateString();
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('The business date is not a YYYY-MM-DD value. No wallet was credited.');

            return 1;
        }

        if ($this->option('dry-run')) {
            try {
                $result = $accrual->runControlled($date, true);
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                return 1;
            }
            $this->report($result, $reconciliation);

            return 0;
        }

        if ($controls->emergencyStopped()) {
            $runs->record($date, PackageIncomeRun::REFUSED, 0, 0, 'Emergency stop is on. No wallet was credited.');
            $this->warn('Emergency stop is on. No wallet was credited.');

            return 0;
        }

        if (! $controls->automaticPostingAllowed() && ! $controls->enabled('daily_financial_cycle_enabled')) {
            $runs->record($date, PackageIncomeRun::REFUSED, 0, 0, 'Financial automation is not armed. No wallet was credited.');
            $this->warn('Financial automation is not armed. No wallet was credited.');

            return 0;
        }

        $started = microtime(true);
        try {
            $result = $accrual->runControlled($date, false, (bool) $this->option('recover-yesterday'));
        } catch (RuntimeException $exception) {
            $runs->record($date, PackageIncomeRun::FAILED, 0, 0, $exception->getMessage());
            $this->error($exception->getMessage());

            return 1;
        } catch (Throwable $exception) {
            $runs->record($date, PackageIncomeRun::FAILED, 0, 0, 'The daily cycle failed. '.$exception->getMessage());
            $this->error('The daily cycle failed. '.$exception->getMessage());

            return 1;
        }

        $seconds = number_format(microtime(true) - $started, 3, '.', '');
        $message = 'ROI credits: '.$result['roi_records'].'. Commission credits: '.$result['commission_records'].'. Duration '.$seconds.'s. Negative balances: '.$reconciliation->negativeBalanceCount().'. No external payout was sent.';
        $runs->record($date, PackageIncomeRun::SUCCEEDED, $result['roi_records'], $result['commission_records'], $message);
        $this->report($result, $reconciliation);
        $this->line('Duration '.$seconds.' seconds.');

        return 0;
    }

    /**
     * @param  array{dry_run: bool, roi_records: int, commission_records: int, skipped: list<string>}  $result
     */
    private function report(array $result, WalletBalanceReconciliation $reconciliation): void
    {
        $this->info(($result['dry_run'] ? 'Dry run' : 'Processed').'.');
        $this->line('ROI records: '.$result['roi_records']);
        $this->line('Level commission records: '.$result['commission_records']);
        foreach ($result['skipped'] as $reason) {
            $this->line('Skipped: '.$reason);
        }
        $this->line('Negative wallet balances: '.$reconciliation->negativeBalanceCount());
        $this->line('No external payout was sent.');
    }
}
