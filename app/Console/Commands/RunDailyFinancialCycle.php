<?php

namespace App\Console\Commands;

use App\Services\PackageIncomeAccrual;
use App\Services\WalletBalanceReconciliation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class RunDailyFinancialCycle extends Command
{
    protected $signature = 'financial:daily {--dry-run : Preview one business date without writing} {--date= : Asia/Kolkata date. An actual run accepts only today.}';

    protected $description = 'Process one Asia/Kolkata business date. Wallet crediting stays off unless its own switch is enabled.';

    public function handle(PackageIncomeAccrual $accrual, WalletBalanceReconciliation $reconciliation): int
    {
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $date = $this->option('date') ?: CarbonImmutable::now($timezone)->toDateString();
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('The business date is not a YYYY-MM-DD value. No wallet was credited.');

            return 1;
        }

        try {
            $result = $accrual->runControlled($date, (bool) $this->option('dry-run'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return 1;
        } catch (Throwable $exception) {
            $this->error('The daily cycle failed. '.$exception->getMessage());

            return 1;
        }

        $this->info(($result['dry_run'] ? 'Dry run' : 'Processed').' '.$date.'.');
        $this->line('ROI records: '.$result['roi_records']);
        $this->line('Level commission records: '.$result['commission_records']);
        foreach ($result['skipped'] as $reason) {
            $this->line('Skipped: '.$reason);
        }
        $this->line('Negative wallet balances: '.$reconciliation->negativeBalanceCount());
        $this->line('No external payout was sent.');

        return 0;
    }
}
